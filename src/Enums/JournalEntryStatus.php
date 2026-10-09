<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

/**
 * Lifecycle State Machine for Journal Entries / Vouchers.
 */
enum JournalEntryStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case POSTED = 'posted';
    case REVERSED = 'reversed';
    case REJECTED = 'rejected';

    /**
     * Check if the voucher can be modified or deleted.
     */
    public function isMutable(): bool
    {
        return match ($this) {
            self::DRAFT, self::REJECTED => true,
            self::PENDING_APPROVAL, self::APPROVED, self::POSTED, self::REVERSED => false,
        };
    }

    /**
     * Check if the voucher is posted and committed to the general ledger.
     */
    public function isPosted(): bool
    {
        return $this === self::POSTED;
    }
}
