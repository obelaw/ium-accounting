<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Obelaw\Ium\Accounting\Base\ModelBase;

/**
 * Anti-Corruption Layer (ACL) Event to COA Account Mapping Rule.
 *
 * Automatically maps to table `ium_accounting_event_account_mappings`.
 */
class EventAccountMapping extends ModelBase
{
    protected $table = 'event_account_mappings';

    protected $fillable = [
        'event_name',
        'debit_account_code',
        'credit_account_code',
        'description_template',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
