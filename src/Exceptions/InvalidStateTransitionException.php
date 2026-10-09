<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when an illegal journal voucher state transition is attempted.
 */
class InvalidStateTransitionException extends DomainException
{
    public static function transition(string $entryNumber, string $from, string $to): self
    {
        return new self(
            sprintf(
                'Invalid state transition for voucher "%s": Cannot transition from "%s" to "%s".',
                $entryNumber,
                $from,
                $to
            )
        );
    }
}
