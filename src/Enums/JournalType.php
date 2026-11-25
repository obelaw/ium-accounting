<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum JournalType: string
{
    case GENERAL = 'general';
    case SALES = 'sales';
    case PURCHASE = 'purchase';
    case BANK = 'bank';
    case CASH = 'cash';
    case FIXED_ASSET = 'fixed_asset';
    case FX = 'fx';

    public function defaultPrefix(): string
    {
        return match ($this) {
            self::GENERAL => 'GJ',
            self::SALES => 'SJ',
            self::PURCHASE => 'PJ',
            self::BANK => 'BNK',
            self::CASH => 'CSH',
            self::FIXED_ASSET => 'FA',
            self::FX => 'FX',
        };
    }
}
