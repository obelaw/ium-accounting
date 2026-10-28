<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\DepreciationScheduleStatus;

/**
 * Fixed Asset Depreciation Schedule Line Item.
 *
 * Automatically maps to table `ium_accounting_depreciation_schedules`.
 */
class DepreciationSchedule extends ModelBase
{
    protected $table = 'depreciation_schedules';

    protected $fillable = [
        'fixed_asset_id',
        'fiscal_period_id',
        'period_sequence',
        'schedule_date',
        'depreciation_amount',
        'accumulated_depreciation_after',
        'net_book_value_after',
        'status',
        'journal_entry_id',
        'posted_at',
        'posted_by',
        'metadata',
    ];

    protected $casts = [
        'fixed_asset_id' => 'integer',
        'fiscal_period_id' => 'integer',
        'period_sequence' => 'integer',
        'schedule_date' => 'date:Y-m-d',
        'depreciation_amount' => 'integer',
        'accumulated_depreciation_after' => 'integer',
        'net_book_value_after' => 'integer',
        'status' => DepreciationScheduleStatus::class,
        'journal_entry_id' => 'integer',
        'posted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function depreciationAmountMajor(int $precision = 2): float
    {
        return round($this->depreciation_amount / (10 ** $precision), $precision);
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
