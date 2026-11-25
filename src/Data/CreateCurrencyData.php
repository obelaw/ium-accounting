<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Support\Money;

final readonly class CreateCurrencyData
{
    public string $code;

    public int $precision;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $code,
        public string $name,
        public string $symbol,
        ?int $precision = null,
        public bool $symbolFirst = true,
        public string $decimalMark = '.',
        public string $thousandsSeparator = ',',
        public bool $isBase = false,
        public bool $isActive = true,
        public float $exchangeRate = 1.0,
        public array $metadata = [],
    ) {
        $normalized = strtoupper(trim($code));
        if (! preg_match('/^[A-Z]{3}$/', $normalized)) {
            throw new InvalidArgumentException("Currency code must be a valid 3-character ISO 4217 code, got [{$code}].");
        }

        $resolvedPrecision = $precision ?? Money::decimalsFor($normalized);
        if ($resolvedPrecision < 0 || $resolvedPrecision > 6) {
            throw new InvalidArgumentException("Currency precision must be between 0 and 6, got {$resolvedPrecision}.");
        }

        if ($exchangeRate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be strictly positive.');
        }

        $this->code = $normalized;
        $this->precision = $resolvedPrecision;
    }
}
