<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\JournalEntry;

use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * Delete a mutable (DRAFT or REJECTED) journal voucher.
 *
 * @extends Action<int, bool>
 */
final class DeleteDraftEntryAction extends Action
{
    protected ?string $ability = 'accounting.journal_entry.delete';

    public function __construct(private readonly AuditRecorder $audit) {}

    protected function handle(mixed $input, Actor $actor): bool
    {
        $entry = JournalEntry::with('lines')->lockForUpdate()->findOrFail((int) $input);

        if (! $entry->status->isMutable()) {
            throw ImmutableJournalEntryException::cannotMutate($entry->entry_number, 'delete');
        }

        $before = $entry->toArray();

        $this->audit->record(
            action: AuditAction::DELETED,
            auditable: $entry,
            before: $before,
            after: null,
            actorId: $actor->id,
            originContext: self::class,
        );

        $entry->lines()->delete();

        return (bool) $entry->delete();
    }
}
