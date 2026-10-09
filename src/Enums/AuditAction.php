<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

/**
 * Immutable Audit Trail Actions.
 */
enum AuditAction: string
{
    case CREATED = 'created';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case POSTED = 'posted';
    case REVERSED = 'reversed';
    case PERIOD_LOCKED = 'period_locked';
    case PERIOD_REOPENED = 'period_reopened';
    case YEAR_END_CLOSED = 'year_end_closed';
}
