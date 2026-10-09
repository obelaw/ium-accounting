<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

/**
 * Journal Entry Line posting side: Debit or Credit.
 */
enum PostingSide: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';

    /**
     * Return the opposite posting side (used for Storno and reversal vouchers).
     */
    public function opposite(): self
    {
        return match ($this) {
            self::DEBIT => self::CREDIT,
            self::CREDIT => self::DEBIT,
        };
    }
}
