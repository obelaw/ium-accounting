<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class UnbalancedJournalEntryException extends AccountingException
{
    public static function forVoucher(string $entryNumber, int $totalDebit, int $totalCredit, int $imbalance): self
    {
        return new self(sprintf(
            'Journal voucher "%s" violates the Zero-Sum invariant: Total debits (%d) do not equal total credits (%d). Imbalance is %d.',
            $entryNumber,
            $totalDebit,
            $totalCredit,
            $imbalance
        ));
    }
}
