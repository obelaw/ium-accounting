<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\JournalEntry;

use Carbon\Carbon;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Events\JournalEntryReversed;
use Obelaw\Ium\Accounting\Exceptions\ReconciliationException;
use Obelaw\Ium\Accounting\Exceptions\ReversalNotAllowedException;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;
use Obelaw\Ium\Facades\Ium;

/**
 * Storno reversal of a POSTED journal entry.
 *
 * @extends Action<array{entry_id: int, reason: string, actor_id?: ?string, reversal_date?: ?string, fiscal_period_id?: ?int}, JournalEntry>
 */
final class ReverseJournalEntryAction extends Action
{
    protected ?string $ability = 'accounting.journal_entry.reverse';

    public function __construct(
        private readonly CreateDraftEntryAction $createDraft,
        private readonly PostJournalEntryAction $postEntry,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): JournalEntry
    {
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Reversal reason is mandatory.');
        }

        $original = JournalEntry::with('lines')->lockForUpdate()->findOrFail((int) $input['entry_id']);
        $actorId = (string) ($input['actor_id'] ?? $actor->id);

        if ($original->status !== JournalEntryStatus::POSTED) {
            throw ReversalNotAllowedException::notPosted($original->entry_number, $original->status->value);
        }

        if ($original->reversal_entry_id !== null) {
            $existingRev = JournalEntry::find($original->reversal_entry_id);
            throw ReversalNotAllowedException::alreadyReversed(
                $original->entry_number,
                $existingRev?->entry_number ?? (string) $original->reversal_entry_id,
            );
        }

        // Prevent reversing an entry whose lines are actively reconciled (must unreconcile first)
        foreach ($original->lines as $line) {
            if ((int) $line->reconciled_amount > 0) {
                throw ReconciliationException::invalid(
                    "Cannot reverse voucher [{$original->entry_number}] because line #{$line->line_number} has reconciled allocations. Unreconcile first."
                );
            }
        }

        $beforeOriginal = $original->toArray();

        $reversalDate = isset($input['reversal_date'])
            ? Carbon::parse((string) $input['reversal_date'])->format('Y-m-d')
            : $original->entry_date->format('Y-m-d');

        $reversalLines = [];
        foreach ($original->lines as $line) {
            $reversalLines[] = new JournalLineData(
                accountId: $line->account_id,
                type: $line->type->opposite(),
                amount: (int) $line->amount,
                amountFunctional: (int) $line->amount_functional,
                costCenterId: $line->cost_center_id,
                taxId: $line->tax_id,
                dueDate: $line->due_date?->format('Y-m-d'),
                narration: sprintf('Storno reversal of %s: %s', $original->entry_number, $reason),
                partyType: $line->party_type,
                partyId: $line->party_id,
            );
        }

        $year = Carbon::parse($reversalDate)->format('Y');
        $reversalNumber = Ium::sequence()->next("accounting.rev.{$year}", "REV-{$year}", 6);

        $reversalDraft = $this->createDraft->execute(new CreateJournalEntryData(
            entryDate: $reversalDate,
            lines: $reversalLines,
            journalId: $original->journal_id,
            fiscalPeriodId: isset($input['fiscal_period_id']) ? (int) $input['fiscal_period_id'] : null,
            entryNumber: $reversalNumber,
            entryType: JournalEntryType::REVERSAL,
            reference: $original->entry_number,
            narration: sprintf('Storno reversal for %s. Reason: %s', $original->entry_number, $reason),
            currency: $original->currency,
            exchangeRate: (float) $original->exchange_rate,
            creatorId: $actorId,
            sourceType: JournalEntry::class,
            sourceId: $original->id,
        ));

        $reversalPosted = $this->postEntry->execute([
            'entry_id' => $reversalDraft->id,
            'actor_id' => $actorId,
            'bypass_approval' => true,
        ]);

        $original->update([
            'status' => JournalEntryStatus::REVERSED,
            'reversal_entry_id' => $reversalPosted->id,
        ]);

        $reversalPosted->update([
            'reversed_entry_id' => $original->id,
        ]);

        $this->audit->record(
            action: AuditAction::REVERSED,
            auditable: $original,
            before: $beforeOriginal,
            after: $original->fresh()->toArray(),
            actorId: $actorId,
            reason: $reason,
            originContext: self::class,
        );

        $this->recordEvent(new JournalEntryReversed($original->fresh(), $reversalPosted->fresh(), $actorId, $reason));

        return $reversalPosted->fresh(['lines.account']);
    }
}
