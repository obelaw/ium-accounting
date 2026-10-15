<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Data\JournalEntryLineDto;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Events\JournalEntryApproved;
use Obelaw\Ium\Accounting\Events\JournalEntryCreated;
use Obelaw\Ium\Accounting\Events\JournalEntryPosted;
use Obelaw\Ium\Accounting\Events\JournalEntryRejected;
use Obelaw\Ium\Accounting\Events\JournalEntryReversed;
use Obelaw\Ium\Accounting\Events\JournalEntrySubmitted;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\FiscalPeriodClosedException;
use Obelaw\Ium\Accounting\Exceptions\InvalidStateTransitionException;
use Obelaw\Ium\Accounting\Exceptions\MakerCheckerViolationException;
use Obelaw\Ium\Accounting\Exceptions\NonPostingAccountException;
use Obelaw\Ium\Accounting\Exceptions\ReversalNotAllowedException;
use Obelaw\Ium\Accounting\Exceptions\UnbalancedJournalEntryException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Models\JournalEntryLine;

class JournalEntryService
{
    public function __construct(
        private readonly ?LedgerService $ledger = null,
        private readonly ?FiscalPeriodService $fiscalPeriods = null,
        private readonly ?AuditService $audit = null
    ) {}

    private function ledgerService(): LedgerService
    {
        return $this->ledger ?? app(LedgerService::class);
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
     * Create a new draft Journal Voucher with line items.
     */
    public function createDraft(CreateJournalEntryDto $dto): JournalEntry
    {
        return DB::transaction(function () use ($dto) {
            $entryDate = Carbon::parse($dto->entryDate)->format('Y-m-d');

            // Resolve fiscal period if not provided
            $periodId = $dto->fiscalPeriodId;
            if ($periodId === null) {
                $period = $this->fiscalPeriodService()->findPeriodForDate($entryDate);
                $periodId = $period?->id;
            }

            $entryNumber = $dto->entryNumber ?? $this->generateNextEntryNumber();

            // Calculate totals and validate accounts
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($dto->lines as $lineDto) {
                $account = Account::find($lineDto->accountId);
                if (!$account) {
                    throw AccountNotFoundException::forId($lineDto->accountId);
                }

                // Invariant: Only leaf-level posting accounts accept lines
                if (!$account->allowsPostings()) {
                    throw NonPostingAccountException::forAccount(
                        $account->code,
                        $account->name,
                        $account->classification->value
                    );
                }

                if ($lineDto->type === PostingSide::DEBIT) {
                    $totalDebit += (int) $lineDto->amount;
                } else {
                    $totalCredit += (int) $lineDto->amount;
                }
            }

            $exchangeRate = $dto->exchangeRate;
            if ($exchangeRate === 1.0 && strtoupper($dto->currency) !== 'USD') {
                try {
                    $currencyService = app(CurrencyService::class);
                    $baseCurrency = $currencyService->getBaseCurrency();
                    if (strtoupper($dto->currency) !== $baseCurrency->code) {
                        $resolvedRate = $currencyService->getRate($dto->currency, $baseCurrency->code, $entryDate);
                        if ($resolvedRate > 0) {
                            $exchangeRate = $resolvedRate;
                        }
                    }
                } catch (\Throwable) {
                    // Fall back to provided rate
                }
            }

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate,
                'fiscal_period_id' => $periodId,
                'status' => JournalEntryStatus::DRAFT,
                'entry_type' => $dto->entryType,
                'narration' => $dto->narration,
                'currency' => $dto->currency,
                'exchange_rate' => $exchangeRate,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'creator_id' => $dto->creatorId,
                'source_type' => $dto->sourceType,
                'source_id' => $dto->sourceId,
                'metadata' => $dto->metadata,
            ]);

            // Persist lines
            $lineIdx = 1;
            foreach ($dto->lines as $lineDto) {
                $functionalAmount = $lineDto->amountFunctional ?? (int) round($lineDto->amount * $exchangeRate);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $lineDto->accountId,
                    'cost_center_id' => $lineDto->costCenterId,
                    'line_number' => $lineDto->lineNumber ?? $lineIdx++,
                    'type' => $lineDto->type,
                    'amount' => (int) $lineDto->amount,
                    'amount_functional' => (int) $functionalAmount,
                    'narration' => $lineDto->narration ?? $dto->narration,
                    'party_type' => $lineDto->partyType,
                    'party_id' => $lineDto->partyId,
                    'metadata' => $lineDto->metadata,
                ]);
            }

            $this->auditService()->record(
                action: AuditAction::CREATED,
                auditable: $entry,
                before: null,
                after: $entry->toArray(),
                actorId: $dto->creatorId,
                originContext: 'JournalEntryService::createDraft'
            );

            event(new JournalEntryCreated($entry, $dto->creatorId));

            return $entry->fresh(['lines.account']);
        });
    }

    /**
     * Submit a draft entry for approval.
     */
    public function submit(int $entryId, ?string $actorId = null): JournalEntry
    {
        return DB::transaction(function () use ($entryId, $actorId) {
            $entry = JournalEntry::with('lines')->findOrFail($entryId);

            if ($entry->status !== JournalEntryStatus::DRAFT && $entry->status !== JournalEntryStatus::REJECTED) {
                throw InvalidStateTransitionException::transition(
                    $entry->entry_number,
                    $entry->status->value,
                    JournalEntryStatus::PENDING_APPROVAL->value
                );
            }

            // Invariant: Zero-sum validation before allowing submission
            $this->assertZeroSum($entry);

            // Invariant: Fiscal period must be valid and allow posting
            if ($entry->fiscal_period_id !== null) {
                $period = FiscalPeriod::find($entry->fiscal_period_id);
                if ($period) {
                    $this->fiscalPeriodService()->assertPeriodAllowsPosting($period);
                }
            }

            $before = $entry->toArray();
            $entry->update([
                'status' => JournalEntryStatus::PENDING_APPROVAL,
                'rejection_reason' => null,
            ]);

            $this->auditService()->record(
                action: AuditAction::SUBMITTED,
                auditable: $entry,
                before: $before,
                after: $entry->fresh()->toArray(),
                actorId: $actorId,
                originContext: 'JournalEntryService::submit'
            );

            event(new JournalEntrySubmitted($entry, $actorId));

            return $entry;
        });
    }

    /**
     * Maker-Checker approval step.
     */
    public function approve(int $entryId, string $approverId): JournalEntry
    {
        return DB::transaction(function () use ($entryId, $approverId) {
            $entry = JournalEntry::findOrFail($entryId);

            if ($entry->status !== JournalEntryStatus::PENDING_APPROVAL) {
                throw InvalidStateTransitionException::transition(
                    $entry->entry_number,
                    $entry->status->value,
                    JournalEntryStatus::APPROVED->value
                );
            }

            // Invariant: Maker-Checker separation
            $strict = Config::get('ium.accounting.maker_checker.strict', true);
            if ($strict && $entry->creator_id !== null && $entry->creator_id === $approverId) {
                throw MakerCheckerViolationException::sameActor($approverId);
            }

            $before = $entry->toArray();
            $entry->update([
                'status' => JournalEntryStatus::APPROVED,
                'approver_id' => $approverId,
            ]);

            $this->auditService()->record(
                action: AuditAction::APPROVED,
                auditable: $entry,
                before: $before,
                after: $entry->fresh()->toArray(),
                actorId: $approverId,
                originContext: 'JournalEntryService::approve'
            );

            event(new JournalEntryApproved($entry, $approverId));

            return $entry;
        });
    }

    /**
     * Reject a voucher back to draft/rejected state with a mandatory reason.
     */
    public function reject(int $entryId, string $actorId, string $reason): JournalEntry
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Rejection reason is mandatory.');
        }

        return DB::transaction(function () use ($entryId, $actorId, $reason) {
            $entry = JournalEntry::findOrFail($entryId);

            if ($entry->status !== JournalEntryStatus::PENDING_APPROVAL) {
                throw InvalidStateTransitionException::transition(
                    $entry->entry_number,
                    $entry->status->value,
                    JournalEntryStatus::REJECTED->value
                );
            }

            $before = $entry->toArray();
            $entry->update([
                'status' => JournalEntryStatus::REJECTED,
                'rejection_reason' => $reason,
            ]);

            $this->auditService()->record(
                action: AuditAction::REJECTED,
                auditable: $entry,
                before: $before,
                after: $entry->fresh()->toArray(),
                actorId: $actorId,
                reason: $reason,
                originContext: 'JournalEntryService::reject'
            );

            event(new JournalEntryRejected($entry, $actorId, $reason));

            return $entry;
        });
    }

    /**
     * Post an approved (or directly balanced draft) journal entry to the General Ledger.
     */
    public function post(int $entryId, string $actorId): JournalEntry
    {
        return DB::transaction(function () use ($entryId, $actorId) {
            $entry = JournalEntry::with('lines.account')->findOrFail($entryId);

            if ($entry->isPosted()) {
                return $entry;
            }

            // Invariant: Enforce zero-sum rule
            $this->assertZeroSum($entry);

            // Invariant: Period lock check
            if ($entry->fiscal_period_id !== null) {
                $period = FiscalPeriod::find($entry->fiscal_period_id);
                if ($period) {
                    $this->fiscalPeriodService()->assertPeriodAllowsPosting($period);
                }
            }

            // Commit postings to GL and update account current balances
            $this->ledgerService()->postEntryToLedger($entry, $actorId);

            $before = $entry->toArray();
            $entry->update([
                'status' => JournalEntryStatus::POSTED,
                'posted_at' => now(),
                'posted_by' => $actorId,
            ]);

            $this->auditService()->record(
                action: AuditAction::POSTED,
                auditable: $entry,
                before: $before,
                after: $entry->fresh()->toArray(),
                actorId: $actorId,
                originContext: 'JournalEntryService::post'
            );

            event(new JournalEntryPosted($entry, $actorId));

            return $entry->fresh();
        });
    }

    /**
     * Storno / Reversal Accounting Pattern:
     * Erroneous posted entries are NEVER deleted; an equal-and-opposite reversal voucher is created.
     */
    public function reverse(
        int $entryId,
        string $actorId,
        string $reason,
        ?string $reversalDate = null,
        ?int $fiscalPeriodId = null
    ): JournalEntry {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Reversal reason is mandatory.');
        }

        return DB::transaction(function () use ($entryId, $actorId, $reason, $reversalDate, $fiscalPeriodId) {
            $original = JournalEntry::with('lines')->findOrFail($entryId);

            if ($original->status !== JournalEntryStatus::POSTED) {
                throw ReversalNotAllowedException::notPosted($original->entry_number, $original->status->value);
            }

            if ($original->reversal_entry_id !== null) {
                $reversalVoucher = JournalEntry::find($original->reversal_entry_id);
                throw ReversalNotAllowedException::alreadyReversed(
                    $original->entry_number,
                    $reversalVoucher?->entry_number ?? (string) $original->reversal_entry_id
                );
            }

            $date = $reversalDate ?? Carbon::parse($original->entry_date)->format('Y-m-d');
            $periodId = $fiscalPeriodId ?? $this->fiscalPeriodService()->findPeriodForDate($date)?->id;

            // Generate opposite lines (Debits become Credits, Credits become Debits)
            $reversalLines = [];
            foreach ($original->lines as $line) {
                $reversalLines[] = new JournalEntryLineDto(
                    accountId: $line->account_id,
                    type: $line->type->opposite(),
                    amount: (int) $line->amount,
                    costCenterId: $line->cost_center_id,
                    amountFunctional: (int) $line->amount_functional,
                    narration: sprintf('Reversal of JV #%s: %s', $original->entry_number, $reason),
                    partyType: $line->party_type,
                    partyId: $line->party_id,
                );
            }

            $reversalNumber = $this->generateNextEntryNumber('REV');

            $reversalDto = new CreateJournalEntryDto(
                entryDate: $date,
                lines: $reversalLines,
                entryNumber: $reversalNumber,
                fiscalPeriodId: $periodId,
                entryType: JournalEntryType::REVERSAL,
                narration: sprintf('Storno reversal for JV #%s. Reason: %s', $original->entry_number, $reason),
                currency: $original->currency,
                exchangeRate: $original->exchange_rate,
                creatorId: $actorId,
                sourceType: JournalEntry::class,
                sourceId: $original->id,
            );

            // Create and immediately post the reversal voucher
            $reversalEntry = $this->createDraft($reversalDto);
            $reversalEntry = $this->post($reversalEntry->id, $actorId);

            // Link cross-references
            $original->update([
                'status' => JournalEntryStatus::REVERSED,
                'reversal_entry_id' => $reversalEntry->id,
            ]);

            $reversalEntry->update([
                'reversed_entry_id' => $original->id,
            ]);

            $this->auditService()->record(
                action: AuditAction::REVERSED,
                auditable: $original,
                before: $original->toArray(),
                after: $original->fresh()->toArray(),
                actorId: $actorId,
                reason: $reason,
                originContext: 'JournalEntryService::reverse'
            );

            event(new JournalEntryReversed($original, $reversalEntry, $actorId, $reason));

            return $reversalEntry;
        });
    }

    /**
     * Enforce the mathematical Zero-Sum Invariant down to currency precision.
     */
    public function assertZeroSum(JournalEntry $entry): void
    {
        $lines = $entry->lines;
        if ($lines->isEmpty()) {
            throw new InvalidArgumentException(sprintf('Voucher "%s" has no line items.', $entry->entry_number));
        }

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            if ($line->type === PostingSide::DEBIT) {
                $totalDebit += (int) $line->amount;
            } else {
                $totalCredit += (int) $line->amount;
            }
        }

        $imbalance = abs($totalDebit - $totalCredit);
        if ($imbalance !== 0) {
            throw UnbalancedJournalEntryException::forVoucher(
                $entry->entry_number,
                $totalDebit,
                $totalCredit,
                $imbalance
            );
        }
    }

    public function generateNextEntryNumber(string $prefix = 'JV'): string
    {
        $year = date('Y');
        $count = JournalEntry::whereYear('created_at', (int) $year)->count() + 1;

        return sprintf('%s-%s-%06d', $prefix, $year, $count);
    }
}
