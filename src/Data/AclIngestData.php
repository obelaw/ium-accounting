<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use Carbon\Carbon;
use InvalidArgumentException;

final readonly class AclIngestData
{
    public string $transactionDate;

    public string $currency;

    /**
     * @param list<array{account_code: string, type: string, amount: int, cost_center_id?: ?int, tax_id?: ?int, due_date?: ?string, narration?: ?string, party_type?: ?string, party_id?: ?int}> $lines
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $eventName,
        public string $documentReference,
        ?string $transactionDate = null,
        public int $amount = 0,
        string $currency = 'USD',
        public ?string $journalCode = null,
        public ?string $debitAccountCode = null,
        public ?string $creditAccountCode = null,
        public ?int $costCenterId = null,
        public ?int $taxId = null,
        public ?string $dueDate = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $narration = null,
        public bool $autoPost = true,
        public array $lines = [],
        public ?string $idempotencyKey = null,
        public ?string $partyType = null,
        public ?int $partyId = null,
        public array $metadata = [],
    ) {
        if ($lines === [] && $amount <= 0) {
            throw new InvalidArgumentException('AclIngestData requires either a positive amount or explicit lines.');
        }

        $this->transactionDate = Carbon::parse($transactionDate ?? date('Y-m-d'))->format('Y-m-d');
        $this->currency = strtoupper(trim($currency));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            eventName: (string) ($payload['event_name'] ?? throw new InvalidArgumentException('event_name is required.')),
            documentReference: (string) ($payload['document_reference'] ?? throw new InvalidArgumentException('document_reference is required.')),
            transactionDate: isset($payload['transaction_date']) ? (string) $payload['transaction_date'] : null,
            amount: (int) ($payload['amount'] ?? 0),
            currency: (string) ($payload['currency'] ?? 'USD'),
            journalCode: isset($payload['journal_code']) ? (string) $payload['journal_code'] : null,
            debitAccountCode: isset($payload['debit_account_code']) ? (string) $payload['debit_account_code'] : null,
            creditAccountCode: isset($payload['credit_account_code']) ? (string) $payload['credit_account_code'] : null,
            costCenterId: isset($payload['cost_center_id']) ? (int) $payload['cost_center_id'] : null,
            taxId: isset($payload['tax_id']) ? (int) $payload['tax_id'] : null,
            dueDate: isset($payload['due_date']) ? (string) $payload['due_date'] : null,
            sourceType: isset($payload['source_type']) ? (string) $payload['source_type'] : null,
            sourceId: isset($payload['source_id']) ? (int) $payload['source_id'] : null,
            narration: isset($payload['narration']) ? (string) $payload['narration'] : null,
            autoPost: (bool) ($payload['auto_post'] ?? true),
            lines: array_values((array) ($payload['lines'] ?? [])),
            idempotencyKey: isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'] : null,
            partyType: isset($payload['party_type']) ? (string) $payload['party_type'] : null,
            partyId: isset($payload['party_id']) ? (int) $payload['party_id'] : null,
            metadata: (array) ($payload['metadata'] ?? []),
        );
    }
}
