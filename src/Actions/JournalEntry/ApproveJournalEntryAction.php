<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\JournalEntry;

use Illuminate\Support\Facades\Config;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Events\JournalEntryApproved;
use Obelaw\Ium\Accounting\Exceptions\InvalidStateTransitionException;
use Obelaw\Ium\Accounting\Exceptions\MakerCheckerViolationException;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<array{entry_id: int, approver_id?: ?string}, JournalEntry>
 */
final class ApproveJournalEntryAction extends Action
{
    protected ?string $ability = 'accounting.journal_entry.approve';

    public function __construct(private readonly AuditRecorder $audit) {}

    protected function handle(mixed $input, Actor $actor): JournalEntry
    {
        $entry = JournalEntry::lockForUpdate()->findOrFail((int) $input['entry_id']);
        $approverId = (string) ($input['approver_id'] ?? $actor->id);

        if ($entry->status !== JournalEntryStatus::PENDING_APPROVAL) {
            throw InvalidStateTransitionException::transition(
                $entry->entry_number,
                $entry->status->value,
                JournalEntryStatus::APPROVED->value,
            );
        }

        $strict = (bool) Config::get('ium.accounting.governance.strict_maker_checker', true);
        if ($strict && $entry->creator_id !== null && $entry->creator_id === $approverId) {
            throw MakerCheckerViolationException::sameActor($approverId);
        }

        $before = $entry->toArray();
        $entry->update([
            'status' => JournalEntryStatus::APPROVED,
            'approver_id' => $approverId,
        ]);

        $fresh = $entry->fresh();

        $this->audit->record(
            action: AuditAction::APPROVED,
            auditable: $fresh,
            before: $before,
            after: $fresh->toArray(),
            actorId: $approverId,
            originContext: self::class,
        );

        $this->recordEvent(new JournalEntryApproved($fresh, $approverId));

        return $fresh;
    }
}
