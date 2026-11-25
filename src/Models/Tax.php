<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\TaxScope;
use Obelaw\Ium\Accounting\Enums\TaxType;

class Tax extends ModelBase
{
    protected $table = 'taxes';

    protected $fillable = [
        'code',
        'name',
        'scope',
        'type',
        'rate_bps',
        'fixed_amount',
        'is_inclusive',
        'is_withholding',
        'tax_account_id',
        'refund_account_id',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'scope' => TaxScope::class,
        'type' => TaxType::class,
        'rate_bps' => 'integer',
        'fixed_amount' => 'integer',
        'is_inclusive' => 'boolean',
        'is_withholding' => 'boolean',
        'tax_account_id' => 'integer',
        'refund_account_id' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function taxAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'tax_account_id');
    }

    public function refundAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'refund_account_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForScope(Builder $query, TaxScope $scope): Builder
    {
        return $query->whereIn('scope', [$scope->value, TaxScope::BOTH->value]);
    }

    /**
     * Human-readable percentage rate (e.g. 1500 bps -> 15.0).
     */
    public function ratePercent(): float
    {
        return $this->rate_bps / 100;
    }
}
