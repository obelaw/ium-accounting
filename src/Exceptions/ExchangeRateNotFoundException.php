<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when an exchange rate cannot be determined for a currency or pair.
 */
class ExchangeRateNotFoundException extends DomainException
{
    public static function forCurrency(string $code, ?string $date = null): self
    {
        $message = sprintf('Exchange rate not found for currency "%s"', strtoupper($code));

        if ($date !== null) {
            $message .= sprintf(' on or before date "%s"', $date);
        }

        return new self($message . '.');
    }

    public static function forPair(string $from, string $to, ?string $date = null): self
    {
        $message = sprintf('Unable to resolve exchange cross-rate from "%s" to "%s"', strtoupper($from), strtoupper($to));

        if ($date !== null) {
            $message .= sprintf(' on or before date "%s"', $date);
        }

        return new self($message . '.');
    }
}
