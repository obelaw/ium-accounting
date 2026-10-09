<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when reversal is requested on an entry that is not in POSTED state or already reversed.
 */
class ReversalNotAllowedException extends DomainException
{
    public static function notPosted(string $entryNumber, string $status): self
    {
        return new self(
            sprintf(
                'Cannot reverse journal voucher "%s" because it is in "%s" state. Only POSTED vouchers can be reversed.',
                $entryNumber,
                $status
            )
        );
    }

    public static function alreadyReversed(string $entryNumber, string $reversalNumber): self
    {
        return new self(
            sprintf(
                'Journal voucher "%s" has already been reversed by voucher "%s".',
                $entryNumber,
                $reversalNumber
            )
        );
    }
}
