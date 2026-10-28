<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Contracts\ExchangeRateProviderInterface;
use Obelaw\Ium\Accounting\Data\CreateCurrencyDto;
use Obelaw\Ium\Accounting\Data\CurrencyConversionResultDto;
use Obelaw\Ium\Accounting\Data\SetExchangeRateDto;
use Obelaw\Ium\Accounting\Data\UpdateCurrencyDto;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Events\BaseCurrencyChanged;
use Obelaw\Ium\Accounting\Events\CurrencyCreated;
use Obelaw\Ium\Accounting\Events\ExchangeRateUpdated;
use Obelaw\Ium\Accounting\Exceptions\BaseCurrencyRateImmutableException;
use Obelaw\Ium\Accounting\Exceptions\CurrencyNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\ExchangeRateNotFoundException;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\ExchangeRate;

class CurrencyService
{
    private ?ExchangeRateProviderInterface $rateProvider = null;

    public function __construct(
        private readonly ?AuditService $audit = null
    ) {}

    private function auditService(): AuditService
    {
        return $this->audit ?? app(AuditService::class);
    }

    /**
     * Set a custom exchange rate driver provider (e.g. ECB, OpenExchangeRates).
     */
    public function setRateProvider(ExchangeRateProviderInterface $provider): self
    {
        $this->rateProvider = $provider;

        return $this;
    }

    /**
     * Create and register a new ISO 4217 Currency.
     */
    public function create(CreateCurrencyDto $dto): Currency
    {
        return DB::transaction(function () use ($dto) {
            $code = strtoupper(trim($dto->code));

            if (Currency::where('code', $code)->exists()) {
                throw new InvalidArgumentException(
                    sprintf('Currency with code "%s" already exists in the registry.', $code)
                );
            }

            $isBase = $dto->isBase;
            $exchangeRate = $isBase ? 1.000000 : $dto->exchangeRate;

            // If new currency is marked as base, demote any previous base currency
            if ($isBase) {
                Currency::where('is_base', true)->update(['is_base' => false]);
            }

            $currency = Currency::create([
                'code' => $code,
                'name' => $dto->name,
                'symbol' => $dto->symbol,
                'precision' => $dto->precision,
                'symbol_first' => $dto->symbolFirst,
                'decimal_mark' => $dto->decimalMark,
                'thousands_separator' => $dto->thousandsSeparator,
                'is_base' => $isBase,
                'is_active' => $dto->isActive,
                'exchange_rate' => $exchangeRate,
                'metadata' => $dto->metadata,
            ]);

            // Create initial exchange rate snapshot for today
            $today = Carbon::today()->format('Y-m-d');
            $inverseRate = $exchangeRate > 0 ? round(1 / $exchangeRate, 6) : 1.000000;

            ExchangeRate::create([
                'currency_id' => $currency->id,
                'currency_code' => $code,
                'rate' => $exchangeRate,
                'inverse_rate' => $inverseRate,
                'effective_date' => $today,
                'source' => 'initial_seed',
                'metadata' => ['created_with_currency' => true],
            ]);

            $this->auditService()->record(
                action: AuditAction::CREATED,
                auditable: $currency,
                before: null,
                after: $currency->toArray(),
                actorId: $dto->actorId,
                originContext: 'CurrencyService::create'
            );

            event(new CurrencyCreated($currency, $dto->actorId));

            return $currency;
        });
    }

    /**
     * Update an existing currency configuration.
     */
    public function update(int|string $identifier, UpdateCurrencyDto $dto): Currency
    {
        return DB::transaction(function () use ($identifier, $dto) {
            $currency = is_int($identifier)
                ? Currency::find($identifier) ?? throw CurrencyNotFoundException::forId($identifier)
                : $this->findByCodeOrFail((string) $identifier);

            $before = $currency->toArray();
            $currency->update($dto->toArray());

            $this->auditService()->record(
                action: AuditAction::UPDATED,
                auditable: $currency,
                before: $before,
                after: $currency->toArray(),
                actorId: $dto->actorId,
                originContext: 'CurrencyService::update'
            );

            return $currency;
        });
    }

    /**
     * Retrieve the organization's single base functional reporting currency.
     */
    public function getBaseCurrency(): Currency
    {
        $base = Currency::where('is_base', true)->first();

        if ($base !== null) {
            return $base;
        }

        // Check if currency configured in config exists
        $configuredCode = strtoupper(config('ium.accounting.currency.functional', 'USD'));
        $found = Currency::where('code', $configuredCode)->first();

        if ($found !== null) {
            $found->update(['is_base' => true, 'exchange_rate' => 1.000000]);

            return $found;
        }

        // Auto-provision default USD base currency if empty
        return $this->create(new CreateCurrencyDto(
            code: 'USD',
            name: 'US Dollar',
            symbol: '$',
            precision: 2,
            symbolFirst: true,
            isBase: true,
            isActive: true,
            exchangeRate: 1.000000,
        ));
    }

    /**
     * Switch the base functional currency to a new currency code.
     */
    public function setBaseCurrency(string $code, ?string $actorId = null): Currency
    {
        return DB::transaction(function () use ($code, $actorId) {
            $target = $this->findByCodeOrFail($code);

            if ($target->isBase()) {
                return $target;
            }

            $previousBase = Currency::where('is_base', true)->first();

            if ($previousBase !== null) {
                $previousBase->update(['is_base' => false]);
            }

            $target->update([
                'is_base' => true,
                'exchange_rate' => 1.000000,
            ]);

            $today = Carbon::today()->format('Y-m-d');
            $existingRate = ExchangeRate::where('currency_code', $target->code)
                ->whereDate('effective_date', $today)
                ->first();

            if ($existingRate !== null) {
                $existingRate->update([
                    'rate' => 1.000000,
                    'inverse_rate' => 1.000000,
                    'source' => 'base_switch',
                ]);
            } else {
                ExchangeRate::create([
                    'currency_id' => $target->id,
                    'currency_code' => $target->code,
                    'effective_date' => $today,
                    'rate' => 1.000000,
                    'inverse_rate' => 1.000000,
                    'source' => 'base_switch',
                ]);
            }

            $this->auditService()->record(
                action: AuditAction::UPDATED,
                auditable: $target,
                before: ['is_base' => false],
                after: ['is_base' => true],
                actorId: $actorId,
                originContext: 'CurrencyService::setBaseCurrency'
            );

            event(new BaseCurrencyChanged($previousBase, $target, $actorId));

            return $target;
        });
    }

    /**
     * Record an exchange rate snapshot for a given currency code.
     *
     * @param string $code ISO 4217 code
     * @param float $rate Exchange rate value
     * @param string|null $date Effective date (YYYY-MM-DD), defaults to today
     * @param string|null $source Source label ('manual', 'ecb', etc.)
     * @param bool $isDirect Direct quotation (1 Foreign = rate Base) vs Indirect (1 Base = rate Foreign)
     * @param string|null $actorId Auditing actor ID
     */
    public function setRate(
        string $code,
        float $rate,
        ?string $date = null,
        ?string $source = 'manual',
        bool $isDirect = true,
        ?string $actorId = null
    ): ExchangeRate {
        return DB::transaction(function () use ($code, $rate, $date, $source, $isDirect, $actorId) {
            $code = strtoupper(trim($code));
            $currency = $this->findByCodeOrFail($code);

            if ($rate <= 0) {
                throw new InvalidArgumentException(
                    sprintf('Exchange rate must be strictly positive (received %F).', $rate)
                );
            }

            if ($currency->isBase()) {
                if (abs($rate - 1.0) > 0.000001) {
                    throw BaseCurrencyRateImmutableException::cannotAlterBaseRate($code, $rate);
                }
                $rate = 1.0;
            }

            $effectiveDate = $date ? Carbon::parse($date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

            if ($isDirect) {
                $directRate = round($rate, 6);
                $inverseRate = $rate > 0 ? round(1 / $rate, 6) : 0.0;
            } else {
                $directRate = $rate > 0 ? round(1 / $rate, 6) : 0.0;
                $inverseRate = round($rate, 6);
            }

            $existingRate = ExchangeRate::where('currency_code', $code)
                ->whereDate('effective_date', $effectiveDate)
                ->first();

            if ($existingRate !== null) {
                $existingRate->update([
                    'rate' => $directRate,
                    'inverse_rate' => $inverseRate,
                    'source' => $source ?? 'manual',
                ]);
                $exchangeRate = $existingRate;
            } else {
                $exchangeRate = ExchangeRate::create([
                    'currency_id' => $currency->id,
                    'currency_code' => $code,
                    'effective_date' => $effectiveDate,
                    'rate' => $directRate,
                    'inverse_rate' => $inverseRate,
                    'source' => $source ?? 'manual',
                ]);
            }

            // Update cached latest exchange rate on the currency if date is latest
            $latestDate = ExchangeRate::where('currency_code', $code)->max('effective_date');
            if ($latestDate === null || $effectiveDate >= $latestDate) {
                $currency->update(['exchange_rate' => $directRate]);
            }

            $this->auditService()->record(
                action: AuditAction::UPDATED,
                auditable: $exchangeRate,
                before: null,
                after: $exchangeRate->toArray(),
                actorId: $actorId,
                originContext: 'CurrencyService::setRate'
            );

            event(new ExchangeRateUpdated($exchangeRate, $actorId));

            return $exchangeRate;
        });
    }

    /**
     * Resolve cross exchange rate between two currencies on a given date.
     *
     * Triangulates through the base functional currency:
     * Cross Rate (from -> to) = RateToBase(from) / RateToBase(to)
     */
    public function getRate(string $from, string $to, ?string $date = null): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return 1.000000;
        }

        $effectiveDate = $date ? Carbon::parse($date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        $baseCurrency = $this->getBaseCurrency();

        $rateFrom = $this->resolveRateToBase($from, $baseCurrency->code, $effectiveDate);
        $rateTo = $this->resolveRateToBase($to, $baseCurrency->code, $effectiveDate);

        if ($rateTo <= 0) {
            throw ExchangeRateNotFoundException::forPair($from, $to, $effectiveDate);
        }

        return round($rateFrom / $rateTo, 6);
    }

    /**
     * Convert an amount between two currencies.
     */
    public function convert(float $amount, string $from, string $to, ?string $date = null): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        $targetCurrency = $this->findByCodeOrFail($to);

        if ($from === $to) {
            return round($amount, $targetCurrency->precision);
        }

        $rate = $this->getRate($from, $to, $date);
        $converted = $amount * $rate;

        return round($converted, $targetCurrency->precision);
    }

    /**
     * Convert an amount and return comprehensive conversion metadata.
     */
    public function convertDetailed(float $amount, string $from, string $to, ?string $date = null): CurrencyConversionResultDto
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));
        $effectiveDate = $date ? Carbon::parse($date)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

        $targetCurrency = $this->findByCodeOrFail($to);
        $rate = $this->getRate($from, $to, $effectiveDate);
        $converted = round($amount * $rate, $targetCurrency->precision);

        return new CurrencyConversionResultDto(
            amount: $amount,
            fromCurrency: $from,
            toCurrency: $to,
            convertedAmount: $converted,
            rate: $rate,
            effectiveDate: $effectiveDate,
            precision: $targetCurrency->precision,
        );
    }

    /**
     * Find a currency by its 3-character ISO code.
     */
    public function findByCode(string $code): ?Currency
    {
        return Currency::where('code', strtoupper(trim($code)))->first();
    }

    /**
     * Find a currency by code or throw CurrencyNotFoundException.
     */
    public function findByCodeOrFail(string $code): Currency
    {
        $cleanCode = strtoupper(trim($code));

        return $this->findByCode($cleanCode) ?? throw CurrencyNotFoundException::forCode($cleanCode);
    }

    /**
     * Get all registered currencies.
     */
    public function all(bool $onlyActive = true): Collection
    {
        $query = Currency::query();

        if ($onlyActive) {
            $query->active();
        }

        return $query->orderBy('code')->get();
    }

    /**
     * Fetch historical exchange rates for a currency.
     */
    public function getRatesHistory(string $code, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $code = strtoupper(trim($code));
        $query = ExchangeRate::where('currency_code', $code);

        if ($startDate !== null) {
            $query->where('effective_date', '>=', Carbon::parse($startDate)->format('Y-m-d'));
        }

        if ($endDate !== null) {
            $query->where('effective_date', '<=', Carbon::parse($endDate)->format('Y-m-d'));
        }

        return $query->orderBy('effective_date', 'desc')->get();
    }

    /**
     * Pre-populate standard global currencies (ISO 4217).
     */
    /**
     * Pre-populate default ISO 4217 currencies and configure base currency from config.
     *
     * @return array<Currency>
     */
    public function seedDefaultCurrencies(?string $baseCurrency = null): array
    {
        $baseCode = strtoupper(trim($baseCurrency ?? (string) config('twist-accounting.currency.code', 'USD')));

        $this->seedStandardCurrencies($baseCode);

        // Ensure the designated base currency exists and is set as the active base
        $currency = Currency::where('code', $baseCode)->first();
        if ($currency) {
            if (! $currency->is_base) {
                $this->setBaseCurrency($baseCode, 'system-init');
            }
        } else {
            $symbol = (string) config('twist-accounting.currency.symbol', '$');
            $decimals = (int) config('twist-accounting.currency.decimals', 2);

            $this->create(new CreateCurrencyDto(
                code: $baseCode,
                name: $baseCode . ' Currency',
                symbol: $symbol,
                precision: $decimals,
                symbolFirst: true,
                isBase: true,
                isActive: true,
                exchangeRate: 1.0,
            ));
        }

        return Currency::all()->all();
    }

    public function seedStandardCurrencies(string $baseCurrency = 'USD'): void
    {
        $standards = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'precision' => 2, 'symbol_first' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'precision' => 2, 'symbol_first' => false],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'precision' => 2, 'symbol_first' => true],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => 'SAR', 'precision' => 2, 'symbol_first' => false],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'AED', 'precision' => 2, 'symbol_first' => false],
            ['code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => 'EGP', 'precision' => 2, 'symbol_first' => false],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'precision' => 0, 'symbol_first' => true],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'CA$', 'precision' => 2, 'symbol_first' => true],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'precision' => 2, 'symbol_first' => true],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF', 'precision' => 2, 'symbol_first' => true],
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'KWD', 'precision' => 3, 'symbol_first' => false],
            ['code' => 'QAR', 'name' => 'Qatari Riyal', 'symbol' => 'QAR', 'precision' => 2, 'symbol_first' => false],
        ];

        $baseCode = strtoupper(trim($baseCurrency));

        foreach ($standards as $item) {
            if (!Currency::where('code', $item['code'])->exists()) {
                $isBase = $item['code'] === $baseCode;
                $this->create(new CreateCurrencyDto(
                    code: $item['code'],
                    name: $item['name'],
                    symbol: $item['symbol'],
                    precision: $item['precision'],
                    symbolFirst: $item['symbol_first'],
                    isBase: $isBase,
                    isActive: true,
                    exchangeRate: 1.0,
                ));
            }
        }
    }

    /**
     * Resolve exchange rate of a currency against the base functional currency on a given date.
     */
    private function resolveRateToBase(string $code, string $baseCode, string $date): float
    {
        if ($code === $baseCode) {
            return 1.000000;
        }

        // Try historical rate lookup on or before target date
        $historical = ExchangeRate::where('currency_code', $code)
            ->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc')
            ->first();

        if ($historical !== null && $historical->rate > 0) {
            return (float) $historical->rate;
        }

        // Try external driver provider if configured
        if ($this->rateProvider !== null) {
            $providedRate = $this->rateProvider->getRate($code, $baseCode, $date);
            if ($providedRate !== null && $providedRate > 0) {
                return $providedRate;
            }
        }

        // Fall back to currency aggregate's cached rate
        $currency = $this->findByCodeOrFail($code);

        if ($currency->exchange_rate > 0) {
            return (float) $currency->exchange_rate;
        }

        throw ExchangeRateNotFoundException::forCurrency($code, $date);
    }

    /**
     * Convert major currency units to minor units (e.g. 12.50 USD -> 1250 cents).
     */
    public function toSubUnit(float|int $amount, ?string $code = null): int
    {
        $currency = $code !== null ? $this->findByCodeOrFail($code) : $this->getBaseCurrency();

        return $currency->toMinorUnits($amount);
    }

    /**
     * Convert minor sub-units back to major units (e.g. 1250 cents -> 12.50 USD).
     */
    public function fromSubUnit(int $subUnits, ?string $code = null): float
    {
        $currency = $code !== null ? $this->findByCodeOrFail($code) : $this->getBaseCurrency();

        return $currency->toMajorUnits($subUnits);
    }

    /**
     * Format minor units directly into formatted currency string (e.g. 1250 cents -> "$ 12.50").
     */
    public function formatMinor(int $subUnits, ?string $code = null): string
    {
        $currency = $code !== null ? $this->findByCodeOrFail($code) : $this->getBaseCurrency();

        return $currency->formatMinor($subUnits);
    }

    /**
     * Format sub-unit amount into textual label (e.g. "50 Cents" or "50 ¢").
     */
    public function formatSubUnit(int $subUnits, ?string $code = null, bool $useSymbol = true): string
    {
        $currency = $code !== null ? $this->findByCodeOrFail($code) : $this->getBaseCurrency();

        return $currency->formatSubUnit($subUnits, $useSymbol);
    }

    /**
     * Format major and sub-units in compound string (e.g. "$ 12 and 50 Cents").
     */
    public function formatMajorAndSubUnit(float|int $amount, ?string $code = null, bool $useSymbol = false): string
    {
        $currency = $code !== null ? $this->findByCodeOrFail($code) : $this->getBaseCurrency();

        return $currency->formatMajorAndSubUnit($amount, $useSymbol);
    }

    /**
     * Split an amount into major and minor units.
     */
    public function splitUnits(float|int $amount, ?string $code = null): array
    {
        $currency = $code !== null ? $this->findByCodeOrFail($code) : $this->getBaseCurrency();

        return $currency->splitUnits($amount);
    }

    /**
     * Convert minor units directly between currencies.
     */
    public function convertMinor(int $minorAmount, string $from, string $to, ?string $date = null): int
    {
        $fromCurrency = $this->findByCodeOrFail($from);
        $toCurrency = $this->findByCodeOrFail($to);

        $majorFrom = $fromCurrency->toMajorUnits($minorAmount);
        $majorTo = $this->convert($majorFrom, $from, $to, $date);

        return $toCurrency->toMinorUnits($majorTo);
    }
}
