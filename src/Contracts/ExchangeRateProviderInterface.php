<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Contracts;

interface ExchangeRateProviderInterface
{
    /**
     * Fetch the exchange rate for a given currency code relative to base currency.
     */
    public function getRate(string $currencyCode, string $baseCurrencyCode, ?string $date = null): ?float;

    /**
     * Fetch all current rates against base currency.
     *
     * @return array<string, float> [currency_code => rate]
     */
    public function getRates(string $baseCurrencyCode, ?string $date = null): array;
}
