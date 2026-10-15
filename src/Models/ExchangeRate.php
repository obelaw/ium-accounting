<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Obelaw\Ium\Accounting\Base\ModelBase;

/**
 * Exchange Rate historical snapshot model.
 *
 * Automatically maps to table `ium_accounting_exchange_rates`.
 */
class ExchangeRate extends ModelBase
{
    protected $table = 'exchange_rates';

    protected $fillable = [
        'currency_id',
        'currency_code',
        'rate',
        'inverse_rate',
        'effective_date',
        'source',
        'metadata',
    ];

    protected $casts = [
        'currency_id' => 'integer',
        'rate' => 'float',
        'inverse_rate' => 'float',
        'effective_date' => 'date',
        'metadata' => 'array',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function scopeForCurrency(Builder $query, string $currencyCode): Builder
    {
        return $query->where('currency_code', strtoupper($currencyCode));
    }

    public function scopeEffectiveOnOrBefore(Builder $query, string $date): Builder
    {
        return $query->where('effective_date', '<=', $date);
    }
}
