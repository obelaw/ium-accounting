<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum TaxType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';
}
