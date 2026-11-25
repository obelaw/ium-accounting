<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class FiscalPeriodClosedException extends AccountingException
{
    public static function forPeriod(string $periodName, string $status): self
    {
        return new self(sprintf(
            'Posting rejected: Fiscal Period "%s" is "%s" and does not accept transactions.',
            $periodName,
            $status
        ));
    }
}
