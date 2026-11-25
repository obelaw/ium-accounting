<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class ExchangeRateNotFoundException extends AccountingException
{
    public static function forCurrency(string $code, string $date): self
    {
        return new self(sprintf('No exchange rate found for currency "%s" on or before %s.', $code, $date));
    }

    public static function forPair(string $from, string $to, string $date): self
    {
        return new self(sprintf('Unable to resolve cross exchange rate for %s/%s on %s.', $from, $to, $date));
    }
}
