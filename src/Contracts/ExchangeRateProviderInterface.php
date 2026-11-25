<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Contracts;

interface ExchangeRateProviderInterface
{
    public function getRate(string $currencyCode, string $baseCurrencyCode, ?string $date = null): ?float;

    /**
     * @return array<string, float>
     */
    public function getRates(string $baseCurrencyCode, ?string $date = null): array;
}
