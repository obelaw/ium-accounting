<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\FixedAsset;

use Carbon\Carbon;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Actions\JournalEntry\ApproveJournalEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\CreateDraftEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\PostJournalEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\SubmitJournalEntryAction;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\DepreciationScheduleStatus;
use Obelaw\Ium\Accounting\Enums\FixedAssetStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\FiscalPeriodClosedException;
use Obelaw\Ium\Accounting\Models\DepreciationSchedule;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * Post a single depreciation schedule line to the General Ledger (Dr Expense / Cr Accumulated Depreciation).
 *
 * @extends Action<array{schedule_id: int, maker_id?: ?string, checker_id?: ?string, auto_post?: bool}, JournalEntry>
 */
final class ProcessDepreciationLineAction extends Action
{
    protected ?string $ability = 'accounting.fixed_asset.depreciate';

    public function __construct(
        private readonly CreateDraftEntryAction $createDraft,
        private readonly SubmitJournalEntryAction $submitEntry,
        private readonly ApproveJournalEntryAction $approveEntry,
        private readonly PostJournalEntryAction $postEntry,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): JournalEntry
    {
        /** @var DepreciationSchedule $schedule */
        $schedule = DepreciationSchedule::with('fixedAsset')
            ->lockForUpdate()
            ->findOrFail((int) $input['schedule_id']);

        if ($schedule->status === DepreciationScheduleStatus::POSTED) {
            throw new InvalidArgumentException("Depreciation schedule #{$schedule->id} is already posted.");
        }

        $asset = $schedule->fixedAsset;
        $scheduleDate = Carbon::parse($schedule->schedule_date)->format('Y-m-d');
        $makerId = (string) ($input['maker_id'] ?? $actor->id);
        $checkerId = isset($input['checker_id']) ? (string) $input['checker_id'] : null;
        $autoPost = (bool) ($input['auto_post'] ?? true);

        $period = $schedule->fiscal_period_id
            ? FiscalPeriod::find($schedule->fiscal_period_id)
            : FiscalPeriod::whereDate('start_date', '<=', $scheduleDate)->whereDate('end_date', '>=', $scheduleDate)->first();

        if ($period !== null && ! $period->status->allowsPosting(false)) {
            throw FiscalPeriodClosedException::forPeriod($period->name, $period->status->value);
        }

        $amount = (int) $schedule->depreciation_amount;
        $narration = sprintf(
            'Depreciation: %s (%s) - Period %d/%d',
            $asset->name,
            $asset->asset_code,
            $schedule->period_sequence,
            $asset->useful_life_months,
        );

        $entry = $this->createDraft->execute(new CreateJournalEntryData(
            entryDate: $scheduleDate,
            lines: [
                new JournalLineData($asset->depreciation_expense_account_id, PostingSide::DEBIT, $amount, costCenterId: $asset->cost_center_id, narration: $narration),
                new JournalLineData($asset->accumulated_depreciation_account_id, PostingSide::CREDIT, $amount, costCenterId: $asset->cost_center_id, narration: $narration),
            ],
            fiscalPeriodId: $period?->id,
            entryType: JournalEntryType::ADJUSTING,
            reference: $asset->asset_code,
            narration: $narration,
            currency: $asset->currency,
            creatorId: $makerId,
            sourceType: DepreciationSchedule::class,
            sourceId: $schedule->id,
        ));

        $entry = $this->submitEntry->execute(['entry_id' => $entry->id, 'actor_id' => $makerId]);

        if ($autoPost) {
            $approver = $checkerId ?? $makerId;
            $entry = $this->approveEntry->execute(['entry_id' => $entry->id, 'approver_id' => $approver]);
            $entry = $this->postEntry->execute(['entry_id' => $entry->id, 'actor_id' => $approver]);

            $schedule->update([
                'fiscal_period_id' => $period?->id,
                'journal_entry_id' => $entry->id,
                'status' => DepreciationScheduleStatus::POSTED,
                'posted_at' => now(),
                'posted_by' => $approver,
            ]);

            $newAccum = (int) $asset->accumulated_depreciation + $amount;
            $newNbv = (int) $asset->acquisition_cost - $newAccum;

            $asset->update([
                'accumulated_depreciation' => $newAccum,
                'net_book_value' => $newNbv,
                'status' => $newNbv <= (int) $asset->salvage_value
                    ? FixedAssetStatus::FULLY_DEPRECIATED
                    : FixedAssetStatus::ACTIVE,
            ]);

            $this->audit->record(
                action: AuditAction::ASSET_DEPRECIATED,
                auditable: $asset->fresh(),
                after: ['schedule_id' => $schedule->id, 'amount' => $amount, 'nbv' => $newNbv],
                actorId: $approver,
                originContext: self::class,
            );
        } else {
            $schedule->update([
                'fiscal_period_id' => $period?->id,
                'journal_entry_id' => $entry->id,
            ]);
        }

        return $entry->fresh(['lines']);
    }
}
