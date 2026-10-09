<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

/**
 * Categorization of Journal Entries.
 */
enum JournalEntryType: string
{
    case STANDARD = 'standard';
    case ADJUSTING = 'adjusting';
    case CLOSING = 'closing';
    case REVERSAL = 'reversal';
    case OPENING = 'opening';
}
