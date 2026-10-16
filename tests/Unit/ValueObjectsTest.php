<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateCurrencyDto;
use Obelaw\Ium\Accounting\Services\CurrencyService;
use Obelaw\Ium\Accounting\ValueObjects\AccountCode;
use Obelaw\Ium\Accounting\ValueObjects\Money;

test('Money value object performs precise arithmetic and equality checks', function () {
    $m1 = Money::from(100.50, 'USD');
    $m2 = Money::from(50.25, 'USD');

    $sum = $m1->add($m2);
    expect($sum->amount)->toBe(150.75)
        ->and($sum->currency)->toBe('USD');

    $diff = $m1->subtract($m2);
    expect($diff->amount)->toBe(50.25);

    expect($m1->equals(Money::from(100.50, 'USD')))->toBeTrue()
        ->and($m1->isPositive())->toBeTrue()
        ->and(Money::from(-10.0, 'USD')->isPositive())->toBeFalse()
        ->and(Money::from(0.0, 'USD')->isZero())->toBeTrue()
        ->and($m1->isZero())->toBeFalse();
});

test('Money value object converts between minor and major units with custom precision', function () {
    $usd = Money::fromMinor(12345, 'usd');
    expect($usd->amount)->toBe(123.45)
        ->and($usd->currency)->toBe('USD')
        ->and($usd->toMinorUnits())->toBe(12345);

    $kwd = Money::fromMinor(12345, 'KWD', 3);
    expect($kwd->amount)->toBe(12.345)
        ->and($kwd->toMinorUnits(3))->toBe(12345);

    $jpy = Money::fromMinor(500, 'JPY', 0);
    expect($jpy->amount)->toBe(500.0)
        ->and($jpy->toMinorUnits(0))->toBe(500);
});

test('Money value object rejects currency mismatches and non-finite amounts', function () {
    $usd = Money::from(100.0, 'USD');
    $eur = Money::from(100.0, 'EUR');

    expect(fn() => $usd->add($eur))
        ->toThrow(InvalidArgumentException::class, 'Currency mismatch: Cannot operate on USD and EUR.');

    expect(fn() => $usd->subtract($eur))
        ->toThrow(InvalidArgumentException::class, 'Currency mismatch: Cannot operate on USD and EUR.');

    expect(fn() => new Money(INF, 'USD'))
        ->toThrow(InvalidArgumentException::class, 'Money amount must be a finite number.');

    expect(fn() => new Money(NAN, 'USD'))
        ->toThrow(InvalidArgumentException::class, 'Money amount must be a finite number.');

    expect($usd->equals($eur))->toBeFalse()
        ->and($usd->toArray())->toBe(['amount' => 100.0, 'currency' => 'USD'])
        ->and((string) $usd)->toBe('USD 100.0000')
        ->and($usd->convertTo('USD'))->toBe($usd);
});

test('Money value object converts to another currency using CurrencyService', function () {
    /** @var CurrencyService $currencyService */
    $currencyService = app(CurrencyService::class);
    $currencyService->create(new CreateCurrencyDto(
        code: 'USD',
        name: 'US Dollar',
        symbol: '$',
        isBase: true,
        exchangeRate: 1.0,
    ));
    $currencyService->create(new CreateCurrencyDto(
        code: 'EUR',
        name: 'Euro',
        symbol: '€',
        isBase: false,
        exchangeRate: 1.10,
    ));

    $eurMoney = Money::from(100.0, 'EUR');
    $usdMoney = $eurMoney->convertTo('USD', $currencyService);

    expect($usdMoney->currency)->toBe('USD')
        ->and($usdMoney->amount)->toBe(110.0);
});

test('AccountCode value object validates and formats properly', function () {
    $code = AccountCode::from('1010.01');
    expect((string) $code)->toBe('1010.01')
        ->and($code->value)->toBe('1010.01');

    expect(fn() => AccountCode::from('   '))->toThrow(InvalidArgumentException::class, 'Account code cannot be empty.');
    expect(fn() => AccountCode::from('1010#invalid!'))->toThrow(InvalidArgumentException::class, 'contains invalid characters');
});

test('AccountCode value object supports equality comparison', function () {
    $c1 = AccountCode::from('1110-A_01');
    $c2 = AccountCode::from('1110-A_01');
    $c3 = AccountCode::from('1110-B_01');

    expect($c1->equals($c2))->toBeTrue()
        ->and($c1->equals($c3))->toBeFalse();
});
