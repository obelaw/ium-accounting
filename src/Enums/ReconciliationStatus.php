<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum ReconciliationStatus: string
{
    case NONE = 'none';
    case OPEN = 'open';
    case PARTIAL = 'partial';
    case RECONCILED = 'reconciled';
}
