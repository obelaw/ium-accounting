<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when a currency cannot be found in the registry.
 */
class CurrencyNotFoundException extends DomainException
{
    public static function forCode(string $code): self
    {
        return new self(
            sprintf('Currency with ISO code "%s" not found in the currency registry.', strtoupper($code))
        );
    }

    public static function forId(int $id): self
    {
        return new self(
            sprintf('Currency with ID %d not found in the currency registry.', $id)
        );
    }
}
