<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when attempting to post into a closed or locked fiscal period.
 */
class FiscalPeriodClosedException extends DomainException
{
    public static function forPeriod(string $periodName, string $status): self
    {
        return new self(
            sprintf(
                'Closed fiscal period lock violated: Cannot post transactions into fiscal period "%s" because its status is "%s".',
                $periodName,
                $status
            )
        );
    }
}
