<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum FiscalPeriodStatus: string
{
    case OPEN = 'open';
    case SOFT_CLOSED = 'soft_closed';
    case LOCKED = 'locked';

    public function allowsPosting(bool $isPrivileged = false): bool
    {
        return match ($this) {
            self::OPEN => true,
            self::SOFT_CLOSED => $isPrivileged,
            self::LOCKED => false,
        };
    }
}
