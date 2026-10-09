<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;

/**
 * Monthly or Quarterly Fiscal Accounting Period.
 *
 * Automatically maps to table `ium_accounting_fiscal_periods`.
 */
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
    ];

    protected $casts = [
        'fiscal_year_id' => 'integer',
        'period_number' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => FiscalPeriodStatus::class,
        'locked_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'fiscal_period_id');
    }

    public function ledgerPostings(): HasMany
    {
        return $this->hasMany(GeneralLedgerPosting::class, 'fiscal_period_id');
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
