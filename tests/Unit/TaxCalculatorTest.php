<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountData;
use Obelaw\Ium\Accounting\Data\CreateTaxData;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\TaxScope;
use Obelaw\Ium\Accounting\Enums\TaxType;

it('calculates exclusive, inclusive, fixed, withholding, and compound taxes accurately in minor units', function () {
    $vatAcc = ium()->accounting()->accounts()->create(new CreateAccountData('2150', 'VAT Payable', AccountType::LIABILITY));
    $whtAcc = ium()->accounting()->accounts()->create(new CreateAccountData('2160', 'WHT Payable', AccountType::LIABILITY));

    $vat15Excl = ium()->accounting()->taxes()->create(new CreateTaxData(
        code: 'VAT15',
        name: 'VAT 15% Exclusive',
        taxAccountId: $vatAcc->id,
        scope: TaxScope::SALES,
        type: TaxType::PERCENTAGE,
        rateBps: 1500,
        isInclusive: false,
    ));

    // Exclusive: $100.00 (10000) + 15% = $15.00 (1500) -> $115.00 (11500)
    $excl = ium()->accounting()->taxes()->calculate(10000, $vat15Excl);
    expect($excl->netAmount)->toBe(10000)
        ->and($excl->totalTax)->toBe(1500)
        ->and($excl->grossAmount)->toBe(11500);

    $vat15Incl = ium()->accounting()->taxes()->create(new CreateTaxData(
        code: 'VAT15_INCL',
        name: 'VAT 15% Inclusive',
        taxAccountId: $vatAcc->id,
        scope: TaxScope::SALES,
        type: TaxType::PERCENTAGE,
        rateBps: 1500,
        isInclusive: true,
    ));

    // Inclusive: $115.00 (11500) -> Net $100.00 (10000) + VAT $15.00 (1500) = Gross $115.00 exact
    $incl = ium()->accounting()->taxes()->calculate(11500, $vat15Incl);
    expect($incl->netAmount)->toBe(10000)
        ->and($incl->totalTax)->toBe(1500)
        ->and($incl->grossAmount)->toBe(11500);

    // Inclusive odd amount ($100.00 = 10000): Net + Tax MUST equal Gross exact (no penny loss)
    $inclOdd = ium()->accounting()->taxes()->calculate(10000, $vat15Incl);
    expect($inclOdd->netAmount + $inclOdd->totalTax)->toBe(10000)
        ->and($inclOdd->grossAmount)->toBe(10000);

    // Compound: VAT 15% + Withholding 5% on $1,000.00 (100000)
    $wht5 = ium()->accounting()->taxes()->create(new CreateTaxData(
        code: 'WHT5',
        name: 'Withholding Tax 5%',
        taxAccountId: $whtAcc->id,
        scope: TaxScope::BOTH,
        type: TaxType::PERCENTAGE,
        rateBps: 500,
        isWithholding: true,
    ));

    $compound = ium()->accounting()->taxes()->calculateGroup(100000, [$vat15Excl, $wht5]);
    expect($compound->netAmount)->toBe(100000)
        ->and($compound->totalTax)->toBe(15000)
        ->and($compound->totalWithholding)->toBe(5000)
        ->and($compound->grossAmount)->toBe(110000)
        ->and($compound->lines)->toHaveCount(2);

    // Fixed stamp duty: $2.50 (250)
    $stamp = ium()->accounting()->taxes()->create(new CreateTaxData(
        code: 'STAMP',
        name: 'Fixed Stamp Duty',
        taxAccountId: $vatAcc->id,
        type: TaxType::FIXED,
        fixedAmount: 250,
    ));

    $fixed = ium()->accounting()->taxes()->calculate(5000, $stamp);
    expect($fixed->totalTax)->toBe(250)
        ->and($fixed->grossAmount)->toBe(5250);
});
