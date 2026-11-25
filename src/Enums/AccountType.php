<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum AccountType: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';
    case EQUITY = 'equity';
    case REVENUE = 'revenue';
    case EXPENSE = 'expense';

    public function naturalNormalBalance(): NormalBalance
    {
        return match ($this) {
            self::ASSET, self::EXPENSE => NormalBalance::DEBIT,
            self::LIABILITY, self::EQUITY, self::REVENUE => NormalBalance::CREDIT,
        };
    }

    public function isBalanceSheet(): bool
    {
        return in_array($this, [self::ASSET, self::LIABILITY, self::EQUITY], true);
    }

    public function isNominal(): bool
    {
        return in_array($this, [self::REVENUE, self::EXPENSE], true);
    }
}
