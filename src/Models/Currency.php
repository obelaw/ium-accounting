<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Obelaw\Ium\Accounting\Base\ModelBase;

/**
 * Currency aggregate model.
 *
 * Automatically maps to table `ium_accounting_currencies`.
 */
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

    public function latestExchangeRate(): HasOne
    {
        return $this->hasOne(ExchangeRate::class, 'currency_id')
            ->latestOfMany('effective_date');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBase(Builder $query): Builder
    {
        return $query->where('is_base', true);
    }

    public function isBase(): bool
    {
        return (bool) $this->is_base;
    }

    /**
     * Format a monetary amount according to this currency's display rules.
     */
    public function format(float $amount): string
    {
        $formattedNumber = number_format(
            $amount,
            $this->precision,
            $this->decimal_mark ?? '.',
            $this->thousands_separator ?? ','
        );

        if ($this->symbol_first) {
            return sprintf('%s %s', $this->symbol, $formattedNumber);
        }

        return sprintf('%s %s', $formattedNumber, $this->symbol);
    }

    public function subUnitMultiplier(): int
    {
        return (int) (10 ** $this->precision);
    }

    public function toMinorUnits(float|int $amount): int
    {
        return (int) round(((float) $amount) * $this->subUnitMultiplier());
    }

    public function toMajorUnits(int $minorUnits): float
    {
        $mult = $this->subUnitMultiplier();

        return $mult > 0 ? round($minorUnits / $mult, $this->precision) : (float) $minorUnits;
    }

    public function formatMinor(int $minorUnits): string
    {
        return $this->format($this->toMajorUnits($minorUnits));
    }

    public function subUnitName(): string
    {
        return match (strtoupper($this->code)) {
            'USD', 'EUR', 'CAD', 'AUD' => 'Cents',
            'GBP' => 'Pence',
            'EGP' => 'Piasters',
            'SAR' => 'Halalas',
            'AED', 'KWD', 'BHD', 'IQD', 'JOD' => 'Fils',
            default => 'Cents',
        };
    }

    public function subUnitSymbol(): string
    {
        return match (strtoupper($this->code)) {
            'USD' => '¢',
            'EUR' => 'c',
            'GBP' => 'p',
            'EGP' => 'pt',
            default => '¢',
        };
    }

    public function formatSubUnit(int $subUnits, bool $useSymbol = true): string
    {
        $symbol = $useSymbol ? $this->subUnitSymbol() : $this->subUnitName();

        return sprintf('%d %s', $subUnits, $symbol);
    }

    public function splitUnits(float|int $amount): array
    {
        $floatVal = (float) $amount;
        $major = (int) floor(abs($floatVal));
        $mult = $this->subUnitMultiplier();
        $minor = $mult > 0 ? (int) round((abs($floatVal) - $major) * $mult) : 0;

        return [
            'major' => $major,
            'minor' => $minor,
            'formatted' => $this->format($floatVal),
        ];
    }

    public function formatMajorAndSubUnit(float|int $amount, bool $useSymbol = false): string
    {
        $split = $this->splitUnits($amount);
        $majorStr = number_format($split['major'], 0, $this->decimal_mark ?? '.', $this->thousands_separator ?? ',');
        $subUnitLabel = $useSymbol ? $this->subUnitSymbol() : $this->subUnitName();

        if ($this->symbol_first) {
            return sprintf('%s %s and %d %s', $this->symbol, $majorStr, $split['minor'], $subUnitLabel);
        }

        return sprintf('%s %s and %d %s', $majorStr, $this->symbol, $split['minor'], $subUnitLabel);
    }
}
