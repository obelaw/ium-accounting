<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum DepreciationScheduleStatus: string
{
    case PENDING = 'pending';
    case POSTED = 'posted';
    case REVERSED = 'reversed';
    case SKIPPED = 'skipped';
}
