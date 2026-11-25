<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\TaxScope;
use Obelaw\Ium\Accounting\Enums\TaxType;

final readonly class CreateTaxData
{
    public string $code;

    /**
     * @param int $rateBps Basis points (e.g. 1500 = 15.00%, 1400 = 14.00%, 500 = 5.00%).
     * @param int $fixedAmount Integer minor units when TaxType::FIXED.
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $code,
        public string $name,
        public int $taxAccountId,
        public TaxScope $scope = TaxScope::BOTH,
        public TaxType $type = TaxType::PERCENTAGE,
        public int $rateBps = 0,
        public int $fixedAmount = 0,
        public bool $isInclusive = false,
        public bool $isWithholding = false,
        public ?int $refundAccountId = null,
        public bool $isActive = true,
        public array $metadata = [],
    ) {
        $clean = strtoupper(trim($code));
        if ($clean === '') {
            throw new InvalidArgumentException('Tax code cannot be empty.');
        }

        if ($type === TaxType::PERCENTAGE && ($rateBps < 0 || $rateBps > 10000)) {
            throw new InvalidArgumentException('Percentage tax rate_bps must be between 0 and 10000 (0% - 100%).');
        }

        if ($type === TaxType::FIXED && $fixedAmount < 0) {
            throw new InvalidArgumentException('Fixed tax amount cannot be negative.');
        }

        $this->code = $clean;
    }
}
