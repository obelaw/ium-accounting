<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class ReconciliationException extends AccountingException
{
    public static function invalid(string $message): self
    {
        return new self($message);
    }
}
