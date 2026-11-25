<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class CurrencyNotFoundException extends AccountingException
{
    public static function forCode(string $code): self
    {
        return new self(sprintf('Currency "%s" is not registered.', $code));
    }

    public static function forId(int $id): self
    {
        return new self(sprintf('Currency #%d is not registered.', $id));
    }
}
