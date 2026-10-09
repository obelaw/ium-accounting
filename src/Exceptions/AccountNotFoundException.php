<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when an account cannot be found in the COA.
 */
class AccountNotFoundException extends DomainException
{
    public static function forCode(string $code): self
    {
        return new self(
            sprintf('Account with code "%s" not found in the Chart of Accounts.', $code)
        );
    }

    public static function forId(int $id): self
    {
        return new self(
            sprintf('Account with ID %d not found in the Chart of Accounts.', $id)
        );
    }
}
