<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum JournalEntryType: string
{
    case STANDARD = 'standard';
    case ADJUSTING = 'adjusting';
    case CLOSING = 'closing';
    case OPENING = 'opening';
    case REVERSAL = 'reversal';
    case FX_REVALUATION = 'fx_revaluation';
}
