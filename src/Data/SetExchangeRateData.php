<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;

final readonly class SetExchangeRateData
{
    public string $currencyCode;

    public function __construct(
        string $currencyCode,
        public float $rate,
        public ?string $effectiveDate = null,
        public string $source = 'manual',
        public bool $isDirect = true,
    ) {
        $normalized = strtoupper(trim($currencyCode));
        if (! preg_match('/^[A-Z]{3}$/', $normalized)) {
            throw new InvalidArgumentException("Invalid ISO 4217 currency code [{$currencyCode}].");
        }

        if ($rate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be strictly positive.');
        }

        $this->currencyCode = $normalized;
    }
}
