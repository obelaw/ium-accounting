<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\JournalEntry;

use Obelaw\Ium\Accounting\Data\UpdateJournalEntryData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\ReconciliationStatus;
use Obelaw\Ium\Accounting\Exceptions\InvalidStateTransitionException;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Models\JournalEntryLine;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;
use Obelaw\Ium\Accounting\Support\JournalLineBuilder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * Edit an unposted (DRAFT or REJECTED) journal voucher and atomically replace its lines.
 *
 * @extends Action<UpdateJournalEntryData, JournalEntry>
 */
final class UpdateDraftEntryAction extends Action
{
    protected ?string $ability = 'accounting.journal_entry.update';

    public function __construct(
        private readonly JournalLineBuilder $lineBuilder,
        private readonly ExchangeRateResolver $fxResolver,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): JournalEntry
    {
        $entry = JournalEntry::with('lines')->lockForUpdate()->findOrFail($input->entryId);

        if (! $entry->status->isMutable()) {
            throw InvalidStateTransitionException::transition(
                $entry->entry_number,
                $entry->status->value,
                'edited',
            );
        }

        $before = $entry->toArray();

        $entryDate = $input->entryDate ?? $entry->entry_date->format('Y-m-d');
        $currency = $input->currency ?? $entry->currency;
        $exchangeRate = $input->exchangeRate ?? (
            $input->currency !== null || $input->entryDate !== null
                ? $this->fxResolver->resolveRateForEntry($currency, $entryDate)
                : (float) $entry->exchange_rate
        );

        $updates = [
            'entry_date' => $entryDate,
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'status' => JournalEntryStatus::DRAFT,
            'rejection_reason' => null,
        ];

        if ($input->journalId !== null) {
            $updates['journal_id'] = $input->journalId;
        }
        if ($input->fiscalPeriodId !== null) {
            $updates['fiscal_period_id'] = $input->fiscalPeriodId;
        }
        if ($input->reference !== null) {
            $updates['reference'] = $input->reference;
        }
        if ($input->narration !== null) {
            $updates['narration'] = $input->narration;
        }
        if ($input->metadata !== null) {
            $updates['metadata'] = $input->metadata;
        }

        if ($input->lines !== null) {
            $sums = $this->lineBuilder->validateAndSum($input->lines);
            $functionalAmounts = $this->lineBuilder->allocateFunctional(
                $input->lines,
                $exchangeRate,
                $currency,
                $this->fxResolver->baseCurrencyCode(),
            );

            $updates['total_debit'] = $sums['total_debit'];
            $updates['total_credit'] = $sums['total_credit'];

            $entry->lines()->delete();

            $lineNumber = 1;
            foreach ($input->lines as $idx => $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line->accountId,
                    'cost_center_id' => $line->costCenterId,
                    'tax_id' => $line->taxId,
                    'line_number' => $line->lineNumber ?? $lineNumber++,
                    'type' => $line->type,
                    'amount' => $line->amount,
                    'amount_functional' => $functionalAmounts[$idx],
                    'due_date' => $line->dueDate,
                    'narration' => $line->narration ?? ($input->narration ?? $entry->narration),
                    'party_type' => $line->partyType,
                    'party_id' => $line->partyId,
                    'reconciled_amount' => 0,
                    'reconciled_functional' => 0,
                    'reconciliation_status' => ReconciliationStatus::NONE,
                    'metadata' => $line->metadata,
                ]);
            }
        }

        $entry->update($updates);
        $fresh = $entry->fresh(['lines.account', 'journal']);

        $this->audit->record(
            action: AuditAction::UPDATED,
            auditable: $fresh,
            before: $before,
            after: $fresh->toArray(),
            actorId: $actor->id,
            originContext: self::class,
        );

        return $fresh;
    }
}
