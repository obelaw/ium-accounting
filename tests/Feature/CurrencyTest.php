<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Obelaw\Ium\Accounting\Data\CreateCurrencyDto;
use Obelaw\Ium\Accounting\Data\UpdateCurrencyDto;
use Obelaw\Ium\Accounting\Events\BaseCurrencyChanged;
use Obelaw\Ium\Accounting\Events\CurrencyCreated;
use Obelaw\Ium\Accounting\Events\ExchangeRateUpdated;
use Obelaw\Ium\Accounting\Exceptions\BaseCurrencyRateImmutableException;
use Obelaw\Ium\Accounting\Exceptions\CurrencyNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\ExchangeRateNotFoundException;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\ExchangeRate;
use Obelaw\Ium\Accounting\Services\CurrencyService;
use Obelaw\Ium\Accounting\ValueObjects\Money;

it('resolves the currencies service via central ium fluent DSL', function () {
    expect(ium()->accounting()->currencies())->toBeInstanceOf(CurrencyService::class);
});

it('can register a new ISO 4217 currency with initial exchange rate', function () {
    Event::fake([CurrencyCreated::class]);

    $currency = ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'EUR',
        name: 'Euro',
        symbol: '€',
        precision: 2,
        symbolFirst: false,
        exchangeRate: 1.085000,
        actorId: 'user-finance-1'
    ));

    expect($currency)->toBeInstanceOf(Currency::class);
    expect($currency->code)->toBe('EUR');
    expect($currency->name)->toBe('Euro');
    expect($currency->symbol)->toBe('€');
    expect($currency->precision)->toBe(2);
    expect($currency->exchange_rate)->toBe(1.085000);
    expect($currency->isBase())->toBeFalse();

    // Verifies initial rate snapshot was persisted
    $rate = ExchangeRate::where('currency_code', 'EUR')->first();
    expect($rate)->not->toBeNull();
    expect($rate->rate)->toBe(1.085000);
    expect($rate->inverse_rate)->toBe(round(1 / 1.085000, 6));

    Event::assertDispatched(CurrencyCreated::class);
});

it('enforces unique ISO currency codes', function () {
    ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'GBP',
        name: 'British Pound',
        symbol: '£',
    ));

    expect(fn () => ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'GBP',
        name: 'Duplicate Pound',
        symbol: '£',
    )))->toThrow(InvalidArgumentException::class, 'Currency with code "GBP" already exists');
});

it('prohibits altering the exchange rate of the base functional currency', function () {
    $base = ium()->accounting()->currencies()->getBaseCurrency();
    expect($base->isBase())->toBeTrue();
    expect($base->exchange_rate)->toBe(1.0);

    expect(fn () => ium()->accounting()->currencies()->setRate(
        code: $base->code,
        rate: 1.250000
    ))->toThrow(BaseCurrencyRateImmutableException::class);
});

it('computes direct and inverse rates accurately', function () {
    Event::fake([ExchangeRateUpdated::class]);

    // Create SAR currency
    ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'SAR',
        name: 'Saudi Riyal',
        symbol: 'SAR',
        precision: 2,
        symbolFirst: false,
    ));

    // Set indirect quotation (1 USD = 3.75 SAR)
    $rate = ium()->accounting()->currencies()->setRate(
        code: 'SAR',
        rate: 3.75,
        isDirect: false,
        source: 'central_bank',
        actorId: 'treasury-agent'
    );

    expect($rate->inverse_rate)->toBe(3.75);
    expect($rate->rate)->toBe(round(1 / 3.75, 6)); // ~0.266667

    Event::assertDispatched(ExchangeRateUpdated::class);
});

it('converts amounts directly between foreign and base currency', function () {
    // Ensure base currency is USD
    $base = ium()->accounting()->currencies()->getBaseCurrency();
    expect($base->code)->toBe('USD');

    // Create EUR with direct rate 1 EUR = 1.10 USD
    ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'EUR',
        name: 'Euro',
        symbol: '€',
        precision: 2,
        symbolFirst: false,
        exchangeRate: 1.100000,
    ));

    // Convert 100 EUR to USD
    $usdAmount = ium()->accounting()->currencies()->convert(100.0, 'EUR', 'USD');
    expect($usdAmount)->toBe(110.0);

    // Convert 110 USD to EUR
    $eurAmount = ium()->accounting()->currencies()->convert(110.0, 'USD', 'EUR');
    expect($eurAmount)->toBe(100.0);
});

it('triangulates conversions between two non-base foreign currencies', function () {
    // Base = USD
    ium()->accounting()->currencies()->getBaseCurrency();

    // EUR: 1 EUR = 1.08 USD
    ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'EUR',
        name: 'Euro',
        symbol: '€',
        precision: 2,
        exchangeRate: 1.080000,
    ));

    // GBP: 1 GBP = 1.25 USD
    ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'GBP',
        name: 'British Pound',
        symbol: '£',
        precision: 2,
        exchangeRate: 1.250000,
    ));

    // Cross rate EUR -> GBP: 1.08 / 1.25 = 0.864
    $rate = ium()->accounting()->currencies()->getRate('EUR', 'GBP');
    expect($rate)->toBe(0.864);

    // Convert 1000 EUR to GBP => 864.00 GBP
    $gbpAmount = ium()->accounting()->currencies()->convert(1000.0, 'EUR', 'GBP');
    expect($gbpAmount)->toBe(864.0);

    // Convert detailed DTO
    $detail = ium()->accounting()->currencies()->convertDetailed(1000.0, 'EUR', 'GBP');
    expect($detail->fromCurrency)->toBe('EUR');
    expect($detail->toCurrency)->toBe('GBP');
    expect($detail->convertedAmount)->toBe(864.0);
    expect($detail->rate)->toBe(0.864);
});

it('resolves historical exchange rates based on effective date cutoff', function () {
    ium()->accounting()->currencies()->getBaseCurrency();

    $jpy = ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'JPY',
        name: 'Japanese Yen',
        symbol: '¥',
        precision: 0,
        exchangeRate: 0.006500,
    ));

    // Set rate on 2026-01-01: 1 JPY = 0.006700 USD
    ium()->accounting()->currencies()->setRate('JPY', 0.006700, date: '2026-01-01');

    // Set rate on 2026-02-01: 1 JPY = 0.007000 USD
    ium()->accounting()->currencies()->setRate('JPY', 0.007000, date: '2026-02-01');

    // Conversion on 2026-01-15 resolves rate of 2026-01-01 (0.006700)
    $rateMidJan = ium()->accounting()->currencies()->getRate('JPY', 'USD', '2026-01-15');
    expect($rateMidJan)->toBe(0.006700);

    // Conversion on 2026-02-15 resolves rate of 2026-02-01 (0.007000)
    $rateMidFeb = ium()->accounting()->currencies()->getRate('JPY', 'USD', '2026-02-15');
    expect($rateMidFeb)->toBe(0.007000);

    // 10,000 JPY on 2026-01-15 = 67 USD
    $converted = ium()->accounting()->currencies()->convert(10000, 'JPY', 'USD', '2026-01-15');
    expect($converted)->toBe(67.0);
});

it('allows switching the base reporting currency', function () {
    Event::fake([BaseCurrencyChanged::class]);

    $oldBase = ium()->accounting()->currencies()->getBaseCurrency();
    expect($oldBase->code)->toBe('USD');

    $eur = ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'EUR',
        name: 'Euro',
        symbol: '€',
        exchangeRate: 1.080000,
    ));

    $newBase = ium()->accounting()->currencies()->setBaseCurrency('EUR', actorId: 'cfo-admin');

    expect($newBase->isBase())->toBeTrue();
    expect($newBase->exchange_rate)->toBe(1.0);

    $refreshedOld = Currency::where('code', 'USD')->first();
    expect($refreshedOld->isBase())->toBeFalse();

    Event::assertDispatched(BaseCurrencyChanged::class);
});

it('formats currency strings according to precision and symbol placement', function () {
    $usd = ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'USD',
        name: 'US Dollar',
        symbol: '$',
        precision: 2,
        symbolFirst: true,
    ));

    $kwd = ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'KWD',
        name: 'Kuwaiti Dinar',
        symbol: 'KWD',
        precision: 3,
        symbolFirst: false,
    ));

    expect($usd->format(1250.5))->toBe('$ 1,250.50');
    expect($kwd->format(500.125))->toBe('500.125 KWD');
});

it('integrates Money value object with CurrencyService conversion', function () {
    ium()->accounting()->currencies()->getBaseCurrency();

    ium()->accounting()->currencies()->create(new CreateCurrencyDto(
        code: 'EUR',
        name: 'Euro',
        symbol: '€',
        precision: 2,
        exchangeRate: 1.100000,
    ));

    $eurMoney = Money::from(200.0, 'EUR');
    $usdMoney = $eurMoney->convertTo('USD');

    expect($usdMoney->amount)->toBe(220.0);
    expect($usdMoney->currency)->toBe('USD');
});

it('seeds standard international ISO currencies', function () {
    ium()->accounting()->currencies()->seedStandardCurrencies('USD');

    $codes = Currency::pluck('code')->all();

    expect($codes)->toContain('USD', 'EUR', 'GBP', 'SAR', 'AED', 'EGP', 'JPY', 'KWD');
    expect(Currency::where('code', 'USD')->first()->isBase())->toBeTrue();
});
