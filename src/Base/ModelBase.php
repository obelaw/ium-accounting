<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Base;

use Obelaw\Ium\Bases\ModelBase as StackBaseModel;

/**
 * Accounting module base model.
 *
 * Applies the `ium_accounting_` table prefix.
 */
class ModelBase extends StackBaseModel
{
    /**
     * Module name for table prefixing ('ium_accounting_').
     *
     * @var string|null $module
     */
    protected ?string $module = 'accounting_';
}
