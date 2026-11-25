<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Support;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Contracts\ExchangeRateProviderInterface;
use Obelaw\Ium\Accounting\Data\CreateCurrencyData;
use Obelaw\Ium\Accounting\Exceptions\CurrencyNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\ExchangeRateNotFoundException;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\ExchangeRate;
use Obelaw\Ium\Facades\Ium;
use Obelaw\Ium\Support\Money;

final class ExchangeRateResolver
{
    private ?ExchangeRateProviderInterface $provider = null;

    public function setProvider(?ExchangeRateProviderInterface $provider): void
    {
        $this->provider = $provider;
    }

    public function baseCurrency(): Currency
    {
        $base = Currency::where('is_base', true)->first();
        if ($base !== null) {
            return $base;
        }

        $code = strtoupper((string) Ium::settings()->get(
            'ium.accounting.currency.functional',
            config('ium.accounting.currency.functional', 'USD'),
        ));

        $existing = Currency::where('code', $code)->first();
        if ($existing !== null) {
            $existing->update(['is_base' => true, 'exchange_rate' => 1.0]);

            return $existing;
        }

        $symbol = (string) Ium::settings()->get(
            'ium.accounting.currency.symbol',
            config('ium.accounting.currency.symbol', $code === 'USD' ? '$' : $code),
        );

        $data = new CreateCurrencyData(
            code: $code,
            name: $code === 'USD' ? 'US Dollar' : "{$code} Currency",
            symbol: $symbol,
            precision: Money::decimalsFor($code),
            isBase: true,
        );

        return Currency::create([
            'code' => $data->code,
            'name' => $data->name,
            'symbol' => $data->symbol,
            'precision' => $data->precision,
            'symbol_first' => true,
            'decimal_mark' => '.',
            'thousands_separator' => ',',
            'is_base' => true,
            'is_active' => true,
            'exchange_rate' => 1.0,
        ]);
    }

    public function baseCurrencyCode(): string
    {
        $base = Currency::where('is_base', true)->first();
        if ($base !== null) {
            return $base->code;
        }

        return strtoupper((string) Ium::settings()->get(
            'ium.accounting.currency.functional',
            config('ium.accounting.currency.functional', 'USD'),
        ));
    }

    public function basePrecision(): int
    {
        $base = Currency::where('is_base', true)->first();
        if ($base !== null) {
            return $base->precision;
        }

        return Money::decimalsFor($this->baseCurrencyCode());
    }

    public function getRate(string $from, string $to, ?string $date = null): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return 1.0;
        }

        $effectiveDate = $date ? Carbon::parse($date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        $baseCode = $this->baseCurrency()->code;

        $rateFrom = $this->resolveToBase($from, $baseCode, $effectiveDate);
        $rateTo = $this->resolveToBase($to, $baseCode, $effectiveDate);

        if ($rateTo <= 0) {
            throw ExchangeRateNotFoundException::forPair($from, $to, $effectiveDate);
        }

        return round($rateFrom / $rateTo, 8);
    }

    public function resolveRateForEntry(string $currencyCode, string $entryDate, ?float $explicitRate = null): float
    {
        if ($explicitRate !== null) {
            return $explicitRate;
        }

        $code = strtoupper(trim($currencyCode));
        $baseCode = $this->baseCurrencyCode();

        if ($code === $baseCode) {
            return 1.0;
        }

        try {
            return $this->getRate($code, $baseCode, $entryDate);
        } catch (\Throwable) {
            return 1.0;
        }
    }

    public function convertMinor(int $minorAmount, string $from, string $to, ?string $date = null): int
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return $minorAmount;
        }

        $fromPrecision = Currency::where('code', $from)->value('precision') ?? Money::decimalsFor($from);
        $toPrecision = Currency::where('code', $to)->value('precision') ?? Money::decimalsFor($to);
        $rate = $this->getRate($from, $to, $date);

        $scaleFactor = (10 ** (int) $toPrecision) / (10 ** (int) $fromPrecision);

        return (int) round($minorAmount * $rate * $scaleFactor, 0, PHP_ROUND_HALF_UP);
    }

    private function resolveToBase(string $code, string $baseCode, string $date): float
    {
        if ($code === $baseCode) {
            return 1.0;
        }

        $historical = ExchangeRate::where('currency_code', $code)
            ->whereDate('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc')
            ->first();

        if ($historical !== null && $historical->rate > 0) {
            return (float) $historical->rate;
        }

        if ($this->provider !== null) {
            $provided = $this->provider->getRate($code, $baseCode, $date);
            if ($provided !== null && $provided > 0) {
                return $provided;
            }
        }

        $currency = Currency::where('code', $code)->first()
            ?? throw CurrencyNotFoundException::forCode($code);

        if ($currency->exchange_rate > 0) {
            return (float) $currency->exchange_rate;
        }

        throw ExchangeRateNotFoundException::forCurrency($code, $date);
    }
}
