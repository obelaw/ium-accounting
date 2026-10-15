<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;

/**
 * Ingestion DTO for operational upstream events crossing the Anti-Corruption Layer (ACL).
 *
 * Translates upstream events like:
 * - InvoiceCreated (Sales -> Debit AR, Credit Revenue + Tax)
 * - PaymentReceived (Cash/Bank -> Debit Cash, Credit AR)
 * - BillApproved (Purchase -> Debit Expense/Asset, Credit AP)
 * - VendorPaid (Disbursement -> Debit AP, Credit Cash/Bank)
 * - StockAdjustmentPosted (WMS -> Debit/Credit Inventory Asset, Offset Variance)
 * - TillSettled (POS -> Debit Cash In Transit, Credit Sales)
 */
final readonly class AclIngestEventDto
{
    public function __construct(
        public string $eventName,
        public string $documentReference,
        public string $transactionDate,
        public int $amount,
        public string $currency = 'USD',
        public ?string $debitAccountCode = null,
        public ?string $creditAccountCode = null,
        public ?int $costCenterId = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $actorId = null,
        public ?string $narration = null,
        public bool $autoPost = true,
        public array $metadata = [],
    ) {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Event amount must be positive.');
        }
    }

    public static function from(array $data): self
    {
        $eventName = trim((string) ($data['event_name'] ?? throw new InvalidArgumentException('event_name is required.')));
        $docRef = trim((string) ($data['document_reference'] ?? throw new InvalidArgumentException('document_reference is required.')));
        $date = (string) ($data['transaction_date'] ?? date('Y-m-d'));

        if (!isset($data['amount']) && !isset($data['amount_major'])) {
            throw new InvalidArgumentException('amount is required.');
        }

        $multiplier = (int) ($data['multiplier'] ?? 100);
        $isMajor = (bool) ($data['is_major'] ?? false);

        if (isset($data['amount_major'])) {
            $amount = (int) round(((float) $data['amount_major']) * $multiplier);
        } elseif ($isMajor) {
            $amount = (int) round(((float) $data['amount']) * $multiplier);
        } else {
            $amount = (int) round((float) $data['amount']);
        }

        return new self(
            eventName: $eventName,
            documentReference: $docRef,
            transactionDate: $date,
            amount: $amount,
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            debitAccountCode: isset($data['debit_account_code']) ? (string) $data['debit_account_code'] : null,
            creditAccountCode: isset($data['credit_account_code']) ? (string) $data['credit_account_code'] : null,
            costCenterId: isset($data['cost_center_id']) ? (int) $data['cost_center_id'] : null,
            sourceType: isset($data['source_type']) ? (string) $data['source_type'] : null,
            sourceId: isset($data['source_id']) ? (int) $data['source_id'] : null,
            actorId: isset($data['actor_id']) ? (string) $data['actor_id'] : null,
            narration: isset($data['narration']) ? (string) $data['narration'] : null,
            autoPost: (bool) ($data['auto_post'] ?? true),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    public static function fromMajor(
        string $eventName,
        string $documentReference,
        float|int $amountMajor,
        int $multiplier = 100,
        ?string $transactionDate = null,
        string $currency = 'USD',
        ?string $debitAccountCode = null,
        ?string $creditAccountCode = null,
        ?int $costCenterId = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $actorId = null,
        ?string $narration = null,
        bool $autoPost = true,
        array $metadata = [],
    ): self {
        return new self(
            eventName: $eventName,
            documentReference: $documentReference,
            transactionDate: $transactionDate ?? date('Y-m-d'),
            amount: (int) round(((float) $amountMajor) * $multiplier),
            currency: strtoupper($currency),
            debitAccountCode: $debitAccountCode,
            creditAccountCode: $creditAccountCode,
            costCenterId: $costCenterId,
            sourceType: $sourceType,
            sourceId: $sourceId,
            actorId: $actorId,
            narration: $narration,
            autoPost: $autoPost,
            metadata: $metadata,
        );
    }

    public function toMajor(int $multiplier = 100): float
    {
        return round($this->amount / $multiplier, 4);
    }

    public function toArray(): array
    {
        return [
            'event_name' => $this->eventName,
            'document_reference' => $this->documentReference,
            'transaction_date' => $this->transactionDate,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'debit_account_code' => $this->debitAccountCode,
            'credit_account_code' => $this->creditAccountCode,
            'cost_center_id' => $this->costCenterId,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'actor_id' => $this->actorId,
            'narration' => $this->narration,
            'auto_post' => $this->autoPost,
            'metadata' => $this->metadata,
        ];
    }
}
