<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\JournalEntry;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Events\JournalEntryRejected;
use Obelaw\Ium\Accounting\Exceptions\InvalidStateTransitionException;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<array{entry_id: int, reason: string, actor_id?: ?string}, JournalEntry>
 */
final class RejectJournalEntryAction extends Action
{
    protected ?string $ability = 'accounting.journal_entry.reject';

    public function __construct(private readonly AuditRecorder $audit) {}

    protected function handle(mixed $input, Actor $actor): JournalEntry
    {
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Rejection reason is mandatory.');
        }

        $entry = JournalEntry::lockForUpdate()->findOrFail((int) $input['entry_id']);
        $actorId = (string) ($input['actor_id'] ?? $actor->id);

        if ($entry->status !== JournalEntryStatus::PENDING_APPROVAL) {
            throw InvalidStateTransitionException::transition(
                $entry->entry_number,
                $entry->status->value,
                JournalEntryStatus::REJECTED->value,
            );
        }

        $before = $entry->toArray();
        $entry->update([
            'status' => JournalEntryStatus::REJECTED,
            'rejection_reason' => $reason,
        ]);

        $fresh = $entry->fresh();

        $this->audit->record(
            action: AuditAction::REJECTED,
            auditable: $fresh,
            before: $before,
            after: $fresh->toArray(),
            actorId: $actorId,
            reason: $reason,
            originContext: self::class,
        );

        $this->recordEvent(new JournalEntryRejected($fresh, $actorId, $reason));

        return $fresh;
    }
}
