<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum PostingSide: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';

    public function opposite(): self
    {
        return $this === self::DEBIT ? self::CREDIT : self::DEBIT;
    }
}
