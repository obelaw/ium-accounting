<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Base;

use Obelaw\Ium\Bases\MigrationBase as StackMigrationBase;

/**
 * Accounting module base migration.
 *
 * All accounting tables are prefixed with `ium_accounting_` via `$this->prefix`.
 */
abstract class MigrationBase extends StackMigrationBase
{
    /**
     * Module name for table prefixing ('accounting_').
     */
    protected ?string $module = 'accounting_';
}
