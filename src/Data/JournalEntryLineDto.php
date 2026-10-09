<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\PostingSide;

final readonly class JournalEntryLineDto
{
    public function __construct(
        public int $accountId,
        public PostingSide $type,
        public float $amount,
        public ?int $costCenterId = null,
        public ?int $lineNumber = null,
        public ?float $amountFunctional = null,
        public ?string $narration = null,
        public ?string $partyType = null,
        public ?int $partyId = null,
        public array $metadata = [],
    ) {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Journal line amount must be greater than zero.');
        }
    }

    public static function from(array $data): self
    {
        $accountId = (int) ($data['account_id'] ?? throw new InvalidArgumentException('account_id is required.'));

        $rawType = $data['type'] ?? throw new InvalidArgumentException('Line posting side (type) is required.');
        $type = $rawType instanceof PostingSide ? $rawType : PostingSide::from((string) $rawType);

        $amount = (float) ($data['amount'] ?? throw new InvalidArgumentException('Line amount is required.'));

        return new self(
            accountId: $accountId,
            type: $type,
            amount: round($amount, 4),
            costCenterId: isset($data['cost_center_id']) ? (int) $data['cost_center_id'] : null,
            lineNumber: isset($data['line_number']) ? (int) $data['line_number'] : null,
            amountFunctional: isset($data['amount_functional']) ? round((float) $data['amount_functional'], 4) : null,
            narration: isset($data['narration']) ? trim((string) $data['narration']) : null,
            partyType: isset($data['party_type']) ? (string) $data['party_type'] : null,
            partyId: isset($data['party_id']) ? (int) $data['party_id'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'cost_center_id' => $this->costCenterId,
            'line_number' => $this->lineNumber,
            'amount_functional' => $this->amountFunctional,
            'narration' => $this->narration,
            'party_type' => $this->partyType,
            'party_id' => $this->partyId,
            'metadata' => $this->metadata,
        ];
    }
}
