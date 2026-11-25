<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Base;

use Obelaw\Ium\Bases\ModelBase as IumModelBase;

abstract class ModelBase extends IumModelBase
{
    protected ?string $module = 'accounting';
}
