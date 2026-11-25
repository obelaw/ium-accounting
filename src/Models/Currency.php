<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Support\Money;

class Currency extends ModelBase
{
    protected $table = 'currencies';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'precision',
        'symbol_first',
        'decimal_mark',
        'thousands_separator',
        'is_base',
        'is_active',
        'exchange_rate',
        'metadata',
    ];

    protected $casts = [
        'precision' => 'integer',
        'symbol_first' => 'boolean',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'exchange_rate' => 'float',
        'metadata' => 'array',
    ];

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'currency_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isBase(): bool
    {
        return (bool) $this->is_base;
    }

    public function money(int $minorAmount): Money
    {
        return Money::minor($minorAmount, $this->code, $this->precision);
    }

    public function formatMinor(int $minorAmount): string
    {
        $money = $this->money($minorAmount);
        $major = $money->toMajor();
        $sign = str_starts_with($major, '-') ? '-' : '';
        $unsigned = ltrim($major, '-');

        if ($this->precision === 0) {
            $formatted = number_format((float) $unsigned, 0, $this->decimal_mark, $this->thousands_separator);
        } else {
            [$whole, $fraction] = explode('.', $unsigned);
            $formatted = number_format((float) $whole, 0, $this->decimal_mark, $this->thousands_separator)
                . $this->decimal_mark . $fraction;
        }

        return $this->symbol_first
            ? "{$sign}{$this->symbol} {$formatted}"
            : "{$sign}{$formatted} {$this->symbol}";
    }
}
