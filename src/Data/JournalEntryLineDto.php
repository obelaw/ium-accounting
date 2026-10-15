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
        public int $amount,
        public ?int $costCenterId = null,
        public ?int $lineNumber = null,
        public ?int $amountFunctional = null,
        public ?string $narration = null,
        public ?string $partyType = null,
        public ?int $partyId = null,
        public array $metadata = [],
    ) {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Journal line amount must be greater than zero.');
        }

        if ($amountFunctional !== null && $amountFunctional <= 0) {
            throw new InvalidArgumentException('Journal line functional amount must be greater than zero.');
        }
    }

    public static function from(array $data): self
    {
        $accountId = (int) ($data['account_id'] ?? throw new InvalidArgumentException('account_id is required.'));

        $rawType = $data['type'] ?? throw new InvalidArgumentException('Line posting side (type) is required.');
        $type = $rawType instanceof PostingSide ? $rawType : PostingSide::from((string) $rawType);

        if (!isset($data['amount']) && !isset($data['amount_major'])) {
            throw new InvalidArgumentException('Line amount is required.');
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

        $amountFunctional = null;
        if (isset($data['amount_functional_major'])) {
            $amountFunctional = (int) round(((float) $data['amount_functional_major']) * $multiplier);
        } elseif (isset($data['amount_functional'])) {
            $amountFunctional = $isMajor
                ? (int) round(((float) $data['amount_functional']) * $multiplier)
                : (int) round((float) $data['amount_functional']);
        }

        return new self(
            accountId: $accountId,
            type: $type,
            amount: $amount,
            costCenterId: isset($data['cost_center_id']) ? (int) $data['cost_center_id'] : null,
            lineNumber: isset($data['line_number']) ? (int) $data['line_number'] : null,
            amountFunctional: $amountFunctional,
            narration: isset($data['narration']) ? trim((string) $data['narration']) : null,
            partyType: isset($data['party_type']) ? (string) $data['party_type'] : null,
            partyId: isset($data['party_id']) ? (int) $data['party_id'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    public static function fromMajor(
        int $accountId,
        PostingSide $type,
        float|int $amountMajor,
        int $multiplier = 100,
        ?int $costCenterId = null,
        ?int $lineNumber = null,
        float|int|null $amountFunctionalMajor = null,
        ?string $narration = null,
        ?string $partyType = null,
        ?int $partyId = null,
        array $metadata = [],
    ): self {
        return new self(
            accountId: $accountId,
            type: $type,
            amount: (int) round(((float) $amountMajor) * $multiplier),
            costCenterId: $costCenterId,
            lineNumber: $lineNumber,
            amountFunctional: $amountFunctionalMajor !== null ? (int) round(((float) $amountFunctionalMajor) * $multiplier) : null,
            narration: $narration,
            partyType: $partyType,
            partyId: $partyId,
            metadata: $metadata,
        );
    }

    public function toMajor(int $multiplier = 100): float
    {
        return round($this->amount / $multiplier, 4);
    }

    public function toFunctionalMajor(int $multiplier = 100): ?float
    {
        return $this->amountFunctional !== null ? round($this->amountFunctional / $multiplier, 4) : null;
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
