<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Support\Money;

final readonly class JournalLineData
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int $accountId,
        public PostingSide $type,
        public int $amount,
        public ?int $amountFunctional = null,
        public ?int $costCenterId = null,
        public ?int $taxId = null,
        public ?string $dueDate = null,
        public ?string $narration = null,
        public ?string $partyType = null,
        public ?int $partyId = null,
        public ?int $lineNumber = null,
        public array $metadata = [],
    ) {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Journal line amount must be a positive integer in minor units.');
        }
    }

    /**
     * Construct a line from a major-unit monetary value using exact string parsing (zero float drift).
     *
     * @param array<string, mixed> $metadata
     */
    public static function fromMajor(
        int $accountId,
        PostingSide $type,
        int|float|string $amountMajor,
        string $currency = 'USD',
        ?int $costCenterId = null,
        ?int $taxId = null,
        ?string $dueDate = null,
        ?string $narration = null,
        ?string $partyType = null,
        ?int $partyId = null,
        array $metadata = [],
    ): self {
        $money = Money::fromMajor($amountMajor, $currency);

        return new self(
            accountId: $accountId,
            type: $type,
            amount: $money->amount,
            costCenterId: $costCenterId,
            taxId: $taxId,
            dueDate: $dueDate,
            narration: $narration,
            partyType: $partyType,
            partyId: $partyId,
            metadata: $metadata,
        );
    }
}
