<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

/**
 * Analytical Accounting Dimension Types.
 */
enum DimensionType: string
{
    case COST_CENTER = 'cost_center';
    case PROJECT = 'project';
    case DEPARTMENT = 'department';
}
