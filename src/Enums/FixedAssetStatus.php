<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Enums;

enum FixedAssetStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case FULLY_DEPRECIATED = 'fully_depreciated';
    case DISPOSED = 'disposed';
}
