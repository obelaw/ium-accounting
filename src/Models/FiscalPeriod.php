<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;

class FiscalPeriod extends ModelBase
{
    protected $table = 'fiscal_periods';

    protected $fillable = [
        'fiscal_year_id',
        'period_number',
        'name',
        'start_date',
        'end_date',
        'status',
        'locked_at',
        'locked_by',
        'lock_reason',
        'metadata',
    ];

    protected $casts = [
        'fiscal_year_id' => 'integer',
        'period_number' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'status' => FiscalPeriodStatus::class,
        'locked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'fiscal_period_id');
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
