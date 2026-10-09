<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

/**
 * Normal balance side of an account or journal line.
 */
enum NormalBalance: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';
}
