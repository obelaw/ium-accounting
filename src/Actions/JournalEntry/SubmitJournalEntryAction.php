<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\JournalEntry;

use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Events\JournalEntrySubmitted;
use Obelaw\Ium\Accounting\Exceptions\FiscalPeriodClosedException;
use Obelaw\Ium\Accounting\Exceptions\InvalidStateTransitionException;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Accounting\Support\JournalLineBuilder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<array{entry_id: int, actor_id?: ?string}, JournalEntry>
 */
final class SubmitJournalEntryAction extends Action
{
    protected ?string $ability = 'accounting.journal_entry.submit';

    public function __construct(
        private readonly JournalLineBuilder $lineBuilder,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): JournalEntry
    {
        $entry = JournalEntry::with('lines')->lockForUpdate()->findOrFail((int) $input['entry_id']);
        $actorId = (string) ($input['actor_id'] ?? $actor->id);

        if (! $entry->status->isMutable()) {
            throw InvalidStateTransitionException::transition(
                $entry->entry_number,
                $entry->status->value,
                JournalEntryStatus::PENDING_APPROVAL->value,
            );
        }

        $this->lineBuilder->assertZeroSum($entry);

        if ($entry->fiscal_period_id !== null) {
            $period = FiscalPeriod::find($entry->fiscal_period_id);
            if ($period !== null && ! $period->status->allowsPosting(false)) {
                throw FiscalPeriodClosedException::forPeriod($period->name, $period->status->value);
            }
        }

        $before = $entry->toArray();
        $entry->update([
            'status' => JournalEntryStatus::PENDING_APPROVAL,
            'rejection_reason' => null,
        ]);

        $fresh = $entry->fresh();

        $this->audit->record(
            action: AuditAction::SUBMITTED,
            auditable: $fresh,
            before: $before,
            after: $fresh->toArray(),
            actorId: $actorId,
            originContext: self::class,
        );

        $this->recordEvent(new JournalEntrySubmitted($fresh, $actorId));

        return $fresh;
    }
}
