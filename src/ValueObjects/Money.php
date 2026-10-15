<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object for enterprise decimal monetary amounts.
 */
final readonly class Money
{
    public function __construct(
        public float $amount,
        public string $currency = 'USD'
    ) {
        if (!is_finite($amount)) {
            throw new InvalidArgumentException('Money amount must be a finite number.');
        }
    }

    public static function from(float|int|string $amount, string $currency = 'USD'): self
    {
        return new self((float) $amount, strtoupper(trim($currency)));
    }

    public static function fromMinor(int $minorUnits, string $currency = 'USD', ?int $precision = null): self
    {
        $prec = $precision ?? 2;

        return new self(round($minorUnits / (10 ** $prec), $prec), strtoupper(trim($currency)));
    }

    public function toMinorUnits(?int $precision = null): int
    {
        $prec = $precision ?? 2;

        return (int) round($this->amount * (10 ** $prec));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(round($this->amount + $other->amount, 4), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(round($this->amount - $other->amount, 4), $this->currency);
    }

    public function equals(self $other, float $epsilon = 0.0001): bool
    {
        return $this->currency === $other->currency && abs($this->amount - $other->amount) < $epsilon;
    }

    public function isZero(float $epsilon = 0.0001): bool
    {
        return abs($this->amount) < $epsilon;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0.00001;
    }

    /**
     * Convert this Money instance to a target currency using CurrencyService.
     */
    public function convertTo(string $targetCurrency, ?\Obelaw\Ium\Accounting\Services\CurrencyService $service = null, ?string $date = null): self
    {
        $targetCurrency = strtoupper(trim($targetCurrency));
        if ($this->currency === $targetCurrency) {
            return $this;
        }

        $service ??= app(\Obelaw\Ium\Accounting\Services\CurrencyService::class);
        $converted = $service->convert($this->amount, $this->currency, $targetCurrency, $date);

        return new self($converted, $targetCurrency);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                sprintf('Currency mismatch: Cannot operate on %s and %s.', $this->currency, $other->currency)
            );
        }
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    public function __toString(): string
    {
        return sprintf('%s %0.4f', $this->currency, $this->amount);
    }
}
