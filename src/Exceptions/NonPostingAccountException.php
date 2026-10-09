<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when trying to post journal lines to a non-leaf parent aggregator account.
 */
class NonPostingAccountException extends DomainException
{
    public static function forAccount(string $code, string $name, string $classification): self
    {
        return new self(
            sprintf(
                'Chart of Accounts topology violated: Account "%s - %s" is a "%s" aggregator and does not accept direct journal postings. Only leaf-level posting accounts accept line items.',
                $code,
                $name,
                $classification
            )
        );
    }
}
