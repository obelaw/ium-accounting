<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;
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
        ->and(Money::from(0.0, 'USD')->isZero())->toBeTrue();
});

test('AccountCode value object validates and formats properly', function () {
    $code = AccountCode::from('1010.01');
    expect((string) $code)->toBe('1010.01');

    expect(fn() => AccountCode::from('   '))->toThrow(InvalidArgumentException::class);
    expect(fn() => AccountCode::from('1010#invalid!'))->toThrow(InvalidArgumentException::class);
});

test('PostingSide provides accurate opposite representation for Storno reversals', function () {
    expect(PostingSide::DEBIT->opposite())->toBe(PostingSide::CREDIT)
        ->and(PostingSide::CREDIT->opposite())->toBe(PostingSide::DEBIT);
});

test('AccountType maps accurately to normal balances and classification rules', function () {
    expect(AccountType::ASSET->normalBalance())->toBe(NormalBalance::DEBIT)
        ->and(AccountType::EXPENSE->normalBalance())->toBe(NormalBalance::DEBIT)
        ->and(AccountType::LIABILITY->normalBalance())->toBe(NormalBalance::CREDIT)
        ->and(AccountType::EQUITY->normalBalance())->toBe(NormalBalance::CREDIT)
        ->and(AccountType::REVENUE->normalBalance())->toBe(NormalBalance::CREDIT)
        ->and(AccountType::REVENUE->isNominal())->toBeTrue()
        ->and(AccountType::ASSET->isReal())->toBeTrue();
});
