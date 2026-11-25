<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class BaseCurrencyRateImmutableException extends AccountingException
{
    public static function cannotAlterBaseRate(string $code, float $attemptedRate): self
    {
        return new self(sprintf(
            'Cannot set exchange rate of base currency "%s" to %.6f (base currency rate is fixed at 1.000000).',
            $code,
            $attemptedRate
        ));
    }
}
