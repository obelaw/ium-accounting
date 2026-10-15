<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

use DomainException;

/**
 * Thrown when a journal voucher violates the Zero-Sum Invariant (Debits != Credits).
 */
class UnbalancedJournalEntryException extends DomainException
{
    public static function forVoucher(string $entryNumber, int|float $debitSum, int|float $creditSum, int|float $imbalance): self
    {
        $debitDisplay = is_int($debitSum) ? (string) $debitSum : sprintf('%0.4f', $debitSum);
        $creditDisplay = is_int($creditSum) ? (string) $creditSum : sprintf('%0.4f', $creditSum);
        $imbalanceDisplay = is_int($imbalance) ? (string) $imbalance : sprintf('%0.4f', $imbalance);

        return new self(
            sprintf(
                'Zero-sum invariant violated for voucher "%s": Total debits (%s) do not equal total credits (%s). Imbalance is %s.',
                $entryNumber,
                $debitDisplay,
                $creditDisplay,
                $imbalanceDisplay
            )
        );
    }
}
