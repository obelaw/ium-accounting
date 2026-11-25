<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum JournalEntryStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case POSTED = 'posted';
    case REVERSED = 'reversed';

    public function isMutable(): bool
    {
        return in_array($this, [self::DRAFT, self::REJECTED], true);
    }

    public function isFinalized(): bool
    {
        return in_array($this, [self::POSTED, self::REVERSED], true);
    }
}
