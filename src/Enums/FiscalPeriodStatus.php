<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

/**
 * Lifecycle status of a fiscal period or fiscal year.
 */
enum FiscalPeriodStatus: string
{
    case OPEN = 'open';
    case SOFT_CLOSED = 'soft_closed';
    case LOCKED = 'locked';

    /**
     * Check if postings are permitted in this period.
     */
    public function allowsPosting(bool $isPrivilegedAccountant = false): bool
    {
        return match ($this) {
            self::OPEN => true,
            self::SOFT_CLOSED => $isPrivilegedAccountant,
            self::LOCKED => false,
        };
    }
}
