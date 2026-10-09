<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

/**
 * Fundamental Account Types in the Five-Category Accounting Taxonomy.
 */
enum AccountType: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';
    case EQUITY = 'equity';
    case REVENUE = 'revenue';
    case EXPENSE = 'expense';

    /**
     * Determine the normal balance side (Debit or Credit) for this account type.
     */
    public function normalBalance(): NormalBalance
    {
        return match ($this) {
            self::ASSET, self::EXPENSE => NormalBalance::DEBIT,
            self::LIABILITY, self::EQUITY, self::REVENUE => NormalBalance::CREDIT,
        };
    }

    /**
     * Check if this is a temporary / nominal account (closed at year-end).
     */
    public function isNominal(): bool
    {
        return match ($this) {
            self::REVENUE, self::EXPENSE => true,
            self::ASSET, self::LIABILITY, self::EQUITY => false,
        };
    }

    /**
     * Check if this is a permanent / real account (carried forward as opening balance).
     */
    public function isReal(): bool
    {
        return !$this->isNominal();
    }
}
