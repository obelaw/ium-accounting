<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when the approver of a journal entry is identical to its creator.
 */
class MakerCheckerViolationException extends DomainException
{
    public static function sameActor(string $actorId): self
    {
        return new self(
            sprintf(
                'Maker-Checker governance violated: The approver ("%s") cannot be the same actor who created the journal voucher.',
                $actorId
            )
        );
    }
}
