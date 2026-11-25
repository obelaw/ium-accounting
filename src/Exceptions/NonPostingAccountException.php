<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class NonPostingAccountException extends AccountingException
{
    public static function forAccount(string $code, string $name, string $classification): self
    {
        return new self(sprintf(
            'Account [%s - %s] (%s) is a non-posting aggregator account and cannot accept journal lines.',
            $code,
            $name,
            $classification
        ));
    }
}
