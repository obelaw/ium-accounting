<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\JournalEntry;

use Illuminate\Support\Facades\Config;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Enums\ReconciliationStatus;
use Obelaw\Ium\Accounting\Events\JournalEntryPosted;
use Obelaw\Ium\Accounting\Exceptions\FiscalPeriodClosedException;
use Obelaw\Ium\Accounting\Exceptions\InvalidStateTransitionException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Accounting\Support\JournalLineBuilder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * Post an approved (or system-bypassed) journal voucher into the immutable General Ledger.
 *
 * @extends Action<array{entry_id: int, actor_id?: ?string, bypass_approval?: bool, is_privileged?: bool}, JournalEntry>
 */
final class PostJournalEntryAction extends Action
{
    protected ?string $ability = 'accounting.journal_entry.post';

    public function __construct(
        private readonly JournalLineBuilder $lineBuilder,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): JournalEntry
    {
        $entry = JournalEntry::with('lines')->lockForUpdate()->findOrFail((int) $input['entry_id']);
        $actorId = (string) ($input['actor_id'] ?? $actor->id);
        $bypassApproval = (bool) ($input['bypass_approval'] ?? false);
        $isPrivileged = (bool) ($input['is_privileged'] ?? false);

        if ($entry->isPosted()) {
            return $entry;
        }

        $requireApproval = (bool) Config::get('ium.accounting.governance.require_approval', true);
        if ($requireApproval && ! $bypassApproval) {
            if ($entry->status !== JournalEntryStatus::APPROVED) {
                throw InvalidStateTransitionException::transition(
                    $entry->entry_number,
                    $entry->status->value,
                    JournalEntryStatus::POSTED->value,
                );
            }
        } elseif (! in_array($entry->status, [
            JournalEntryStatus::DRAFT,
            JournalEntryStatus::PENDING_APPROVAL,
            JournalEntryStatus::APPROVED,
        ], true)) {
            throw InvalidStateTransitionException::transition(
                $entry->entry_number,
                $entry->status->value,
                JournalEntryStatus::POSTED->value,
            );
        }

        $this->lineBuilder->assertZeroSum($entry);

        if ($entry->fiscal_period_id !== null) {
            $period = FiscalPeriod::find($entry->fiscal_period_id);
            if ($period !== null && ! $period->status->allowsPosting($isPrivileged)) {
                throw FiscalPeriodClosedException::forPeriod($period->name, $period->status->value);
            }
        }

        // Deterministic account row locking ordered by ID to prevent deadlocks
        $accountIds = $entry->lines->pluck('account_id')->unique()->sort()->values()->all();
        $accounts = Account::whereIn('id', $accountIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($entry->lines as $line) {
            /** @var Account $account */
            $account = $accounts->get($line->account_id) ?? Account::lockForUpdate()->findOrFail($line->account_id);

            $debit = $line->type === PostingSide::DEBIT ? (int) $line->amount_functional : 0;
            $credit = $line->type === PostingSide::CREDIT ? (int) $line->amount_functional : 0;
            $amountCurrency = $line->type === PostingSide::DEBIT ? (int) $line->amount : -(int) $line->amount;

            $delta = $account->normal_balance === NormalBalance::DEBIT
                ? ($debit - $credit)
                : ($credit - $debit);

            $newBalance = (int) $account->current_balance + $delta;

            GeneralLedgerPosting::create([
                'account_id' => $account->id,
                'journal_id' => $entry->journal_id,
                'journal_entry_id' => $entry->id,
                'journal_entry_line_id' => $line->id,
                'fiscal_period_id' => $entry->fiscal_period_id,
                'cost_center_id' => $line->cost_center_id,
                'tax_id' => $line->tax_id,
                'party_type' => $line->party_type,
                'party_id' => $line->party_id,
                'posting_date' => $entry->entry_date,
                'currency' => $entry->currency,
                'exchange_rate' => $entry->exchange_rate,
                'debit' => $debit,
                'credit' => $credit,
                'amount_currency' => $amountCurrency,
                'balance_after' => $newBalance,
                'narration' => $line->narration ?? $entry->narration,
                'created_at' => now(),
            ]);

            $account->update(['current_balance' => $newBalance]);
            $account->current_balance = $newBalance;

            // Mark lines on reconcilable accounts (AR/AP/Clearing) as OPEN for subledger reconciliation
            if ($account->is_reconcilable) {
                $line->update(['reconciliation_status' => ReconciliationStatus::OPEN]);
            }
        }

        $before = $entry->toArray();
        $entry->update([
            'status' => JournalEntryStatus::POSTED,
            'posted_at' => now(),
            'posted_by' => $actorId,
        ]);

        $fresh = $entry->fresh(['lines.account', 'journal']);

        $this->audit->record(
            action: AuditAction::POSTED,
            auditable: $fresh,
            before: $before,
            after: $fresh->toArray(),
            actorId: $actorId,
            originContext: self::class,
        );

        $this->recordEvent(new JournalEntryPosted($fresh, $actorId));

        return $fresh;
    }
}
