<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\DepreciationMethod;
use Obelaw\Ium\Accounting\Enums\FixedAssetStatus;

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
        'declining_rate_bps',
        'purchase_date',
        'in_service_date',
        'disposed_at',
        'disposal_proceeds',
        'currency',
        'acquisition_cost',
        'salvage_value',
        'accumulated_depreciation',
        'net_book_value',
        'useful_life_months',
        'asset_account_id',
        'accumulated_depreciation_account_id',
        'depreciation_expense_account_id',
        'disposal_gain_account_id',
        'disposal_loss_account_id',
        'cost_center_id',
        'acquisition_journal_entry_id',
        'disposal_journal_entry_id',
        'creator_id',
        'metadata',
    ];

    protected $casts = [
        'status' => FixedAssetStatus::class,
        'depreciation_method' => DepreciationMethod::class,
        'declining_rate_bps' => 'integer',
        'purchase_date' => 'date:Y-m-d',
        'in_service_date' => 'date:Y-m-d',
        'disposed_at' => 'date:Y-m-d',
        'disposal_proceeds' => 'integer',
        'acquisition_cost' => 'integer',
        'salvage_value' => 'integer',
        'accumulated_depreciation' => 'integer',
        'net_book_value' => 'integer',
        'useful_life_months' => 'integer',
        'asset_account_id' => 'integer',
        'accumulated_depreciation_account_id' => 'integer',
        'depreciation_expense_account_id' => 'integer',
        'disposal_gain_account_id' => 'integer',
        'disposal_loss_account_id' => 'integer',
        'cost_center_id' => 'integer',
        'acquisition_journal_entry_id' => 'integer',
        'disposal_journal_entry_id' => 'integer',
        'metadata' => 'array',
    ];

    public function depreciableBasis(): int
    {
        return max(0, (int) $this->acquisition_cost - (int) $this->salvage_value);
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

    public function disposalGainAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'disposal_gain_account_id');
    }

    public function disposalLossAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'disposal_loss_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function disposalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'disposal_journal_entry_id');
    }
}
