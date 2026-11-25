<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum NormalBalance: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';
}
