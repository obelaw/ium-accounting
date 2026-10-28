<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateFixedAssetDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Data\JournalEntryLineDto;
use Obelaw\Ium\Accounting\Data\UpdateFixedAssetDto;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\DepreciationScheduleStatus;
use Obelaw\Ium\Accounting\Enums\FixedAssetStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\NonPostingAccountException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\DepreciationSchedule;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FixedAsset;
use Obelaw\Ium\Accounting\Models\JournalEntry;

class FixedAssetService
{
    public function __construct(
        private readonly ?JournalEntryService $journalEntries = null,
        private readonly ?FiscalPeriodService $fiscalPeriods = null,
        private readonly ?AuditService $audit = null,
    ) {}

    private function journalEntryService(): JournalEntryService
    {
        return $this->journalEntries ?? app(JournalEntryService::class);
    }

    private function fiscalPeriodService(): FiscalPeriodService
    {
        return $this->fiscalPeriods ?? app(FiscalPeriodService::class);
    }

    private function auditService(): AuditService
    {
        return $this->audit ?? app(AuditService::class);
    }

    /**
     * Register a Fixed Asset and optionally generate its Straight-Line depreciation schedule.
     */
    public function register(CreateFixedAssetDto $dto): FixedAsset
    {
        return DB::transaction(function () use ($dto) {
            $this->validateAssetAccounts(
                $dto->assetAccountId,
                $dto->accumulatedDepreciationAccountId,
                $dto->depreciationExpenseAccountId
            );

            $status = $dto->activateImmediately ? FixedAssetStatus::ACTIVE : FixedAssetStatus::DRAFT;

            $asset = FixedAsset::create([
                'asset_code' => $dto->assetCode,
                'name' => $dto->name,
                'serial_number' => $dto->serialNumber,
                'description' => $dto->description,
                'status' => $status,
                'depreciation_method' => $dto->depreciationMethod,
                'purchase_date' => Carbon::parse($dto->purchaseDate)->format('Y-m-d'),
                'in_service_date' => Carbon::parse($dto->inServiceDate)->format('Y-m-d'),
                'currency' => $dto->currency,
                'acquisition_cost' => $dto->acquisitionCost,
                'salvage_value' => $dto->salvageValue,
                'accumulated_depreciation' => 0,
                'net_book_value' => $dto->acquisitionCost,
                'useful_life_months' => $dto->usefulLifeMonths,
                'asset_account_id' => $dto->assetAccountId,
                'accumulated_depreciation_account_id' => $dto->accumulatedDepreciationAccountId,
                'depreciation_expense_account_id' => $dto->depreciationExpenseAccountId,
                'cost_center_id' => $dto->costCenterId,
                'creator_id' => $dto->creatorId,
                'metadata' => $dto->metadata,
            ]);

            if ($dto->activateImmediately) {
                $this->generateSchedule($asset->id);
            }

            $this->auditService()->record(
                action: AuditAction::CREATED,
                auditable: $asset,
                before: null,
                after: $asset->toArray(),
                actorId: $dto->creatorId,
                originContext: 'FixedAssetService::register'
            );

            return $asset->fresh(['schedules']);
        });
    }

    /**
     * Update a Fixed Asset. Financial schedule fields can only be modified before any schedule is posted.
     */
    public function update(int $assetId, UpdateFixedAssetDto $dto): FixedAsset
    {
        return DB::transaction(function () use ($assetId, $dto) {
            $asset = FixedAsset::with('schedules')->findOrFail($assetId);
            $before = $asset->toArray();

            $hasPostedDepreciation = $asset->schedules()
                ->where('status', DepreciationScheduleStatus::POSTED)
                ->exists();

            if ($hasPostedDepreciation && ($dto->salvageValue !== null || $dto->usefulLifeMonths !== null)) {
                throw new InvalidArgumentException(
                    "Cannot alter salvage value or useful life on asset [{$asset->asset_code}] after depreciation has been posted."
                );
            }

            if ($dto->salvageValue !== null && ($dto->salvageValue < 0 || $dto->salvageValue >= $asset->acquisition_cost)) {
                throw new InvalidArgumentException(
                    'Salvage value must be >= 0 and strictly less than acquisition cost.'
                );
            }

            if ($dto->usefulLifeMonths !== null && $dto->usefulLifeMonths <= 0) {
                throw new InvalidArgumentException('Useful life in months must be greater than zero.');
            }

            $asset->update($dto->toArray());

            if (!$hasPostedDepreciation && ($dto->salvageValue !== null || $dto->usefulLifeMonths !== null)) {
                $this->generateSchedule($asset->id);
            }

            $this->auditService()->record(
                action: AuditAction::UPDATED,
                auditable: $asset,
                before: $before,
                after: $asset->fresh()->toArray(),
                actorId: $dto->actorId,
                originContext: 'FixedAssetService::update'
            );

            return $asset->fresh(['schedules']);
        });
    }

    /**
     * Generate Straight-Line monthly depreciation schedules in integer minor units.
     * Any integer division remainder is absorbed in the final period so Net Book Value == Salvage Value.
     *
     * @return Collection<int, DepreciationSchedule>
     */
    public function generateSchedule(int $assetId): Collection
    {
        return DB::transaction(function () use ($assetId) {
            $asset = FixedAsset::findOrFail($assetId);

            // Remove only unposted pending lines
            $asset->schedules()
                ->where('status', DepreciationScheduleStatus::PENDING)
                ->delete();

            $depreciableBasis = $asset->depreciableBasis();
            $months = $asset->useful_life_months;

            if ($depreciableBasis <= 0 || $months <= 0) {
                return $asset->schedules()->get();
            }

            $monthlyAmount = intdiv($depreciableBasis, $months);
            $runningAccumulated = 0;
            $startDate = Carbon::parse($asset->in_service_date)->startOfMonth();

            for ($seq = 1; $seq <= $months; $seq++) {
                $scheduleDate = $startDate->copy()->addMonthsNoOverflow($seq - 1)->endOfMonth()->format('Y-m-d');

                // Final month absorbs any rounding remainder
                $amount = ($seq === $months)
                    ? ($depreciableBasis - $runningAccumulated)
                    : $monthlyAmount;

                $runningAccumulated += $amount;
                $nbvAfter = $asset->acquisition_cost - $runningAccumulated;

                $period = $this->fiscalPeriodService()->findPeriodForDate($scheduleDate);

                DepreciationSchedule::create([
                    'fixed_asset_id' => $asset->id,
                    'fiscal_period_id' => $period?->id,
                    'period_sequence' => $seq,
                    'schedule_date' => $scheduleDate,
                    'depreciation_amount' => $amount,
                    'accumulated_depreciation_after' => $runningAccumulated,
                    'net_book_value_after' => $nbvAfter,
                    'status' => DepreciationScheduleStatus::PENDING,
                ]);
            }

            return $asset->schedules()->get();
        });
    }

    /**
     * Execute Straight-Line depreciation for a single schedule line, enforcing:
     * 1. FiscalPeriod hard-lock gate (throws FiscalPeriodClosedException)
     * 2. Zero-Sum balance invariant via JournalEntryService (Dr Expense == Cr Accum. Depr.)
     * 3. Maker-Checker segregation of duties
     */
    public function processScheduleLine(
        int $scheduleId,
        string $makerId,
        ?string $checkerId = null,
        bool $autoPost = true
    ): JournalEntry {
        return DB::transaction(function () use ($scheduleId, $makerId, $checkerId, $autoPost) {
            /** @var DepreciationSchedule $schedule */
            $schedule = DepreciationSchedule::with('fixedAsset')->lockForUpdate()->findOrFail($scheduleId);

            if ($schedule->status === DepreciationScheduleStatus::POSTED) {
                throw new InvalidArgumentException("Depreciation schedule #{$schedule->id} is already posted.");
            }

            $asset = $schedule->fixedAsset;
            $scheduleDate = Carbon::parse($schedule->schedule_date)->format('Y-m-d');

            // 1. Resolve and enforce Fiscal Period Hard-Lock Check
            $period = $schedule->fiscal_period_id
                ? FiscalPeriod::find($schedule->fiscal_period_id)
                : $this->fiscalPeriodService()->findPeriodForDate($scheduleDate);

            if ($period !== null) {
                // Throws FiscalPeriodClosedException if period is LOCKED
                $this->fiscalPeriodService()->assertPeriodAllowsPosting($period);
            }

            $amount = (int) $schedule->depreciation_amount;
            $narration = sprintf(
                'Automated Straight-Line Depreciation: %s (%s) - Period %d/%d',
                $asset->name,
                $asset->asset_code,
                $schedule->period_sequence,
                $asset->useful_life_months
            );

            // 2. Build balanced Debit & Credit lines (Zero-Sum Invariant: $amount == $amount)
            $lines = [
                new JournalEntryLineDto(
                    accountId: $asset->depreciation_expense_account_id,
                    type: PostingSide::DEBIT,
                    amount: $amount,
                    costCenterId: $asset->cost_center_id,
                    narration: $narration,
                ),
                new JournalEntryLineDto(
                    accountId: $asset->accumulated_depreciation_account_id,
                    type: PostingSide::CREDIT,
                    amount: $amount,
                    costCenterId: $asset->cost_center_id,
                    narration: $narration,
                ),
            ];

            $voucherDto = new CreateJournalEntryDto(
                entryDate: $scheduleDate,
                lines: $lines,
                fiscalPeriodId: $period?->id,
                entryType: JournalEntryType::ADJUSTING,
                narration: $narration,
                currency: $asset->currency,
                creatorId: $makerId,
                sourceType: DepreciationSchedule::class,
                sourceId: $schedule->id,
                metadata: [
                    'fixed_asset_id' => $asset->id,
                    'asset_code' => $asset->asset_code,
                    'period_sequence' => $schedule->period_sequence,
                ],
            );

            // 3. Draft & Submit via JournalEntryService (enforces assertZeroSum & leaf checks)
            $entry = $this->journalEntryService()->createDraft($voucherDto);
            $entry = $this->journalEntryService()->submit($entry->id, $makerId);

            // 4. Maker-Checker Governance & Posting
            if ($autoPost) {
                $strictMakerChecker = (bool) Config::get('ium.accounting.maker_checker.strict', true);
                $approver = $checkerId ?? ($strictMakerChecker ? null : $makerId);

                if ($approver === null) {
                    throw new InvalidArgumentException(
                        'A distinct checkerId is required to auto-post when strict Maker-Checker governance is enabled.'
                    );
                }

                // Throws MakerCheckerViolationException if $approver === $makerId in strict mode
                $entry = $this->journalEntryService()->approve($entry->id, $approver);
                $entry = $this->journalEntryService()->post($entry->id, $approver);

                // Update Schedule & Asset Book Value upon GL commit
                $schedule->update([
                    'fiscal_period_id' => $period?->id,
                    'journal_entry_id' => $entry->id,
                    'status' => DepreciationScheduleStatus::POSTED,
                    'posted_at' => now(),
                    'posted_by' => $approver,
                ]);

                $newAccumulated = $asset->accumulated_depreciation + $amount;
                $newNbv = $asset->acquisition_cost - $newAccumulated;

                $asset->update([
                    'accumulated_depreciation' => $newAccumulated,
                    'net_book_value' => $newNbv,
                    'status' => ($newNbv <= $asset->salvage_value)
                        ? FixedAssetStatus::FULLY_DEPRECIATED
                        : FixedAssetStatus::ACTIVE,
                ]);
            } else {
                $schedule->update([
                    'fiscal_period_id' => $period?->id,
                    'journal_entry_id' => $entry->id,
                ]);
            }

            return $entry->fresh(['lines']);
        });
    }

    /**
     * Batch-execute all pending depreciation schedules falling within a FiscalPeriod.
     *
     * @return Collection<int, JournalEntry>
     */
    public function runDepreciationForPeriod(
        int $fiscalPeriodId,
        string $makerId,
        ?string $checkerId = null,
        bool $autoPost = true
    ): Collection {
        return DB::transaction(function () use ($fiscalPeriodId, $makerId, $checkerId, $autoPost) {
            $period = FiscalPeriod::findOrFail($fiscalPeriodId);

            // Fail-fast if fiscal period is locked (throws FiscalPeriodClosedException)
            $this->fiscalPeriodService()->assertPeriodAllowsPosting($period);

            $pendingSchedules = DepreciationSchedule::query()
                ->where('status', DepreciationScheduleStatus::PENDING)
                ->whereBetween('schedule_date', [
                    Carbon::parse($period->start_date)->format('Y-m-d'),
                    Carbon::parse($period->end_date)->format('Y-m-d'),
                ])
                ->whereHas('fixedAsset', static fn($q) => $q->where('status', FixedAssetStatus::ACTIVE))
                ->orderBy('schedule_date')
                ->get();

            $entries = new Collection();
            foreach ($pendingSchedules as $schedule) {
                $entries->push(
                    $this->processScheduleLine($schedule->id, $makerId, $checkerId, $autoPost)
                );
            }

            return $entries;
        });
    }

    public function find(int $assetId): ?FixedAsset
    {
        return FixedAsset::with('schedules')->find($assetId);
    }

    public function findByCode(string $assetCode): ?FixedAsset
    {
        return FixedAsset::with('schedules')->where('asset_code', $assetCode)->first();
    }

    /**
     * @return Collection<int, FixedAsset>
     */
    public function list(?FixedAssetStatus $status = null): Collection
    {
        $query = FixedAsset::query()->with('schedules');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query->orderBy('asset_code')->get();
    }

    /**
     * Validate that all 3 Fixed Asset accounts exist, allow leaf postings, and match expected AccountType.
     */
    private function validateAssetAccounts(
        int $assetAccountId,
        int $accumDeprAccountId,
        int $expenseAccountId
    ): void {
        $accounts = [
            'asset' => [$assetAccountId, AccountType::ASSET],
            'accumulated_depreciation' => [$accumDeprAccountId, AccountType::ASSET],
            'depreciation_expense' => [$expenseAccountId, AccountType::EXPENSE],
        ];

        foreach ($accounts as $role => [$id, $expectedType]) {
            $account = Account::find($id);
            if (!$account) {
                throw AccountNotFoundException::forId($id);
            }
            if (!$account->allowsPostings()) {
                throw NonPostingAccountException::forAccount(
                    $account->code,
                    $account->name,
                    $account->classification->value
                );
            }
            if ($account->type !== $expectedType) {
                throw new InvalidArgumentException(
                    "Fixed asset [{$role}] account ({$account->code}) must be of type [{$expectedType->value}], got [{$account->type->value}]."
                );
            }
        }
    }
}
