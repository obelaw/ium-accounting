<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;

/**
 * Fiscal Accounting Year.
 *
 * Automatically maps to table `ium_accounting_fiscal_years`.
 */
class FiscalYear extends ModelBase
{
    protected $table = 'fiscal_years';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => FiscalPeriodStatus::class,
        'closed_at' => 'datetime',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class, 'fiscal_year_id')->orderBy('period_number');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', FiscalPeriodStatus::OPEN->value);
    }

    public function isOpen(): bool
    {
        return $this->status === FiscalPeriodStatus::OPEN;
    }

    public function isLocked(): bool
    {
        return $this->status === FiscalPeriodStatus::LOCKED;
    }
}
