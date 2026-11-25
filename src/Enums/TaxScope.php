<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum TaxScope: string
{
    case SALES = 'sales';
    case PURCHASE = 'purchase';
    case BOTH = 'both';
}
