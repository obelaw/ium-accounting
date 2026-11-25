<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Obelaw\Ium\Accounting\Actions\Acl\IngestAclEventAction;
use Obelaw\Ium\Accounting\Data\AclIngestData;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Models\PostingRule;
use Obelaw\Ium\Outbox\OutboxMessage;

final class AclGateway
{
    public function __construct(private readonly IngestAclEventAction $ingestAction) {}

    public function registerRule(
        string $eventName,
        string $debitAccountCode,
        string $creditAccountCode,
        ?int $journalId = null,
        ?int $taxId = null,
        ?string $descriptionTemplate = null,
    ): PostingRule {
        return PostingRule::updateOrCreate(
            ['event_name' => $eventName],
            [
                'journal_id' => $journalId,
                'debit_account_code' => $debitAccountCode,
                'credit_account_code' => $creditAccountCode,
                'tax_id' => $taxId,
                'description_template' => $descriptionTemplate,
                'is_active' => true,
            ],
        );
    }

    public function ingest(AclIngestData $data): JournalEntry
    {
        return $this->ingestAction->execute($data);
    }

    public function ingestFromOutbox(OutboxMessage $message): JournalEntry
    {
        $payload = is_array($message->payload) ? $message->payload : [];
        $payload['event_name'] ??= $message->topic;
        $payload['document_reference'] ??= $message->idempotency_key ?? ('OUTBOX-' . $message->id);
        $payload['idempotency_key'] ??= $message->idempotency_key ?? ('outbox:' . $message->id);

        return $this->ingest(AclIngestData::fromArray($payload));
    }
}
