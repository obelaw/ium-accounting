<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum DimensionType: string
{
    case COST_CENTER = 'cost_center';
    case DEPARTMENT = 'department';
    case PROJECT = 'project';
    case BUSINESS_UNIT = 'business_unit';
}
