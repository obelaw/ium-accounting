<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class ReversalNotAllowedException extends AccountingException
{
    public static function notPosted(string $entryNumber, string $status): self
    {
        return new self(sprintf(
            'Voucher "%s" cannot be reversed because its status is "%s" (must be "posted").',
            $entryNumber,
            $status
        ));
    }

    public static function alreadyReversed(string $entryNumber, string $reversalNumber): self
    {
        return new self(sprintf(
            'Voucher "%s" has already been reversed by "%s".',
            $entryNumber,
            $reversalNumber
        ));
    }
}
