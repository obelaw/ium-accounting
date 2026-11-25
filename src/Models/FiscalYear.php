<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;

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
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'status' => FiscalPeriodStatus::class,
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class, 'fiscal_year_id')->orderBy('period_number');
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
