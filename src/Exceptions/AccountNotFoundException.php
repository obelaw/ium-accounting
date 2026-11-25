<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class AccountNotFoundException extends AccountingException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('Account #%d was not found in the Chart of Accounts.', $id));
    }

    public static function forCode(string $code): self
    {
        return new self(sprintf('Account with code "%s" was not found in the Chart of Accounts.', $code));
    }
}
