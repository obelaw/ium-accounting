<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when a journal voucher violates the Zero-Sum Invariant (Debits != Credits).
 */
class UnbalancedJournalEntryException extends DomainException
{
    public static function forVoucher(string $entryNumber, float $debitSum, float $creditSum, float $imbalance): self
    {
        return new self(
            sprintf(
                'Zero-sum invariant violated for voucher "%s": Total debits (%0.4f) do not equal total credits (%0.4f). Imbalance is %0.4f.',
                $entryNumber,
                $debitSum,
                $creditSum,
                $imbalance
            )
        );
    }
}
