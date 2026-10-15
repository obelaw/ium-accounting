<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

final readonly class CurrencyConversionResultDto
{
    public function __construct(
        public float $amount,
        public string $fromCurrency,
        public string $toCurrency,
        public float $convertedAmount,
        public float $rate,
        public string $effectiveDate,
        public int $precision,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            fromCurrency: strtoupper((string) $data['from_currency']),
            toCurrency: strtoupper((string) $data['to_currency']),
            convertedAmount: (float) $data['converted_amount'],
            rate: (float) $data['rate'],
            effectiveDate: (string) $data['effective_date'],
            precision: (int) ($data['precision'] ?? 2),
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'from_currency' => $this->fromCurrency,
            'to_currency' => $this->toCurrency,
            'converted_amount' => $this->convertedAmount,
            'rate' => $this->rate,
            'effective_date' => $this->effectiveDate,
            'precision' => $this->precision,
        ];
    }
}
