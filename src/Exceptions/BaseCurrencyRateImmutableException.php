<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when attempting to assign a non-unitary exchange rate to the base functional currency.
 */
class BaseCurrencyRateImmutableException extends DomainException
{
    public static function cannotAlterBaseRate(string $code, float $attemptedRate): self
    {
        return new self(
            sprintf(
                'Base functional currency "%s" rate is strictly immutable and must equal 1.000000 (attempted: %F).',
                strtoupper($code),
                $attemptedRate
            )
        );
    }
}
