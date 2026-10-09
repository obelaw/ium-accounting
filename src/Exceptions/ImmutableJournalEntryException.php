<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when an update or delete is attempted on an immutable posted journal voucher.
 */
class ImmutableJournalEntryException extends DomainException
{
    public static function cannotMutate(string $entryNumber, string $operation = 'update'): self
    {
        return new self(
            sprintf(
                'Ledger immutability violated: Journal voucher "%s" is posted to the General Ledger and cannot be %sd. Corrections must be recorded via reversal vouchers.',
                $entryNumber,
                $operation
            )
        );
    }
}
