<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Journal;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateJournalData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Models\Journal;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<CreateJournalData, Journal>
 */
final class CreateJournalAction extends Action
{
    protected ?string $ability = 'accounting.journal.create';

    public function __construct(private readonly AuditRecorder $audit) {}

    protected function handle(mixed $input, Actor $actor): Journal
    {
        if (Journal::where('code', $input->code)->exists()) {
            throw new InvalidArgumentException("Journal code [{$input->code}] already exists.");
        }

        return Journal::create([
            'code' => $input->code,
            'name' => $input->name,
            'type' => $input->type,
            'sequence_prefix' => $input->sequencePrefix,
            'default_debit_account_id' => $input->defaultDebitAccountId,
            'default_credit_account_id' => $input->defaultCreditAccountId,
            'currency' => $input->currency ? strtoupper(trim($input->currency)) : null,
            'is_active' => $input->isActive,
            'metadata' => $input->metadata,
        ]);
    }

    protected function audit(Actor $actor, mixed $input, mixed $result): void
    {
        $this->audit->record(
            action: AuditAction::CREATED,
            auditable: $result,
            after: $result->toArray(),
            actorId: $actor->id,
            originContext: self::class,
        );
    }
}
