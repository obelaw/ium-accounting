<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\DepreciationMethod;
use Obelaw\Ium\Accounting\Enums\FixedAssetStatus;

/**
 * Fixed Asset Subledger Aggregate Root.
 *
 * Automatically maps to table `ium_accounting_fixed_assets`.
 */
class FixedAsset extends ModelBase
{
    protected $table = 'fixed_assets';

    protected $fillable = [
        'asset_code',
        'name',
        'serial_number',
        'description',
        'status',
        'depreciation_method',
        'purchase_date',
        'in_service_date',
        'disposed_at',
        'currency',
        'acquisition_cost',
        'salvage_value',
        'accumulated_depreciation',
        'net_book_value',
        'useful_life_months',
        'asset_account_id',
        'accumulated_depreciation_account_id',
        'depreciation_expense_account_id',
        'cost_center_id',
        'acquisition_journal_entry_id',
        'creator_id',
        'metadata',
    ];

    protected $casts = [
        'status' => FixedAssetStatus::class,
        'depreciation_method' => DepreciationMethod::class,
        'purchase_date' => 'date:Y-m-d',
        'in_service_date' => 'date:Y-m-d',
        'disposed_at' => 'date:Y-m-d',
        'acquisition_cost' => 'integer',
        'salvage_value' => 'integer',
        'accumulated_depreciation' => 'integer',
        'net_book_value' => 'integer',
        'useful_life_months' => 'integer',
        'asset_account_id' => 'integer',
        'accumulated_depreciation_account_id' => 'integer',
        'depreciation_expense_account_id' => 'integer',
        'cost_center_id' => 'integer',
        'acquisition_journal_entry_id' => 'integer',
        'metadata' => 'array',
    ];

    public function depreciableBasis(): int
    {
        return max(0, (int) $this->acquisition_cost - (int) $this->salvage_value);
    }

    public function acquisitionCostMajor(int $precision = 2): float
    {
        return round($this->acquisition_cost / (10 ** $precision), $precision);
    }

    public function salvageValueMajor(int $precision = 2): float
    {
        return round($this->salvage_value / (10 ** $precision), $precision);
    }

    public function accumulatedDepreciationMajor(int $precision = 2): float
    {
        return round($this->accumulated_depreciation / (10 ** $precision), $precision);
    }

    public function netBookValueMajor(int $precision = 2): float
    {
        return round($this->net_book_value / (10 ** $precision), $precision);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DepreciationSchedule::class, 'fixed_asset_id')->orderBy('period_sequence');
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function acquisitionJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'acquisition_journal_entry_id');
    }
}
