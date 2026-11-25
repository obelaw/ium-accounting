<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountData;
use Obelaw\Ium\Accounting\Data\CreateCostCenterData;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\CreateTaxData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\DimensionType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Enums\TaxScope;

it('generates Opening/Period/Ending Trial Balance, Point-in-Time Balance Sheet with contra-assets, Cost-Center P&L, Account Ledger, Partner Ledger, Aging, and VAT Return', function () {
    $cash = ium()->accounting()->accounts()->create(new CreateAccountData('1010', 'Cash', AccountType::ASSET));
    $ar = ium()->accounting()->accounts()->create(new CreateAccountData('1200', 'AR', AccountType::ASSET, isReconcilable: true));
    $equip = ium()->accounting()->accounts()->create(new CreateAccountData('1500', 'Equipment', AccountType::ASSET));
    $accumDepr = ium()->accounting()->accounts()->create(new CreateAccountData('1509', 'Accum Depr', AccountType::ASSET, normalBalance: NormalBalance::CREDIT));
    $vatOut = ium()->accounting()->accounts()->create(new CreateAccountData('2150', 'VAT Payable', AccountType::LIABILITY));
    $capital = ium()->accounting()->accounts()->create(new CreateAccountData('3010', 'Capital', AccountType::EQUITY));
    $rev = ium()->accounting()->accounts()->create(new CreateAccountData('4010', 'Sales', AccountType::REVENUE));
    $salesRet = ium()->accounting()->accounts()->create(new CreateAccountData('4090', 'Sales Returns', AccountType::REVENUE, normalBalance: NormalBalance::DEBIT));
    $rent = ium()->accounting()->accounts()->create(new CreateAccountData('5010', 'Rent', AccountType::EXPENSE));
    $deprExp = ium()->accounting()->accounts()->create(new CreateAccountData('5500', 'Depr Expense', AccountType::EXPENSE));

    $deptSales = ium()->accounting()->costCenters()->create(new CreateCostCenterData('SALES', 'Sales Dept', DimensionType::DEPARTMENT));
    $deptOps = ium()->accounting()->costCenters()->create(new CreateCostCenterData('OPS', 'Ops Dept', DimensionType::DEPARTMENT));

    $vat15 = ium()->accounting()->taxes()->create(new CreateTaxData('VAT15', 'Standard VAT 15%', $vatOut->id, TaxScope::BOTH, rateBps: 1500));

    $post = static function (CreateJournalEntryData $data): void {
        $e = ium()->accounting()->journalEntries()->createDraft($data);
        ium()->accounting()->journalEntries()->post($e->id, 'sys', bypassApproval: true);
    };

    // Jan 1: Owner invests $1000 Cash + $500 Equipment
    $post(new CreateJournalEntryData('2026-01-01', [
        new JournalLineData($cash->id, PostingSide::DEBIT, 100000),
        new JournalLineData($equip->id, PostingSide::DEBIT, 50000),
        new JournalLineData($capital->id, PostingSide::CREDIT, 150000),
    ]));

    // Jan 15: Credit Sale to Customer #1: $100 + 15% VAT = $115, due Jan 20
    $post(new CreateJournalEntryData('2026-01-15', [
        new JournalLineData($ar->id, PostingSide::DEBIT, 11500, partyType: 'customer', partyId: 1, dueDate: '2026-01-20'),
        new JournalLineData($rev->id, PostingSide::CREDIT, 10000, costCenterId: $deptSales->id),
        new JournalLineData($vatOut->id, PostingSide::CREDIT, 1500, taxId: $vat15->id),
    ]));

    // Feb 10: Sales Return $10 (contra-revenue) + Ops Rent $20 + Input VAT $3 = Cash $23 + Depreciation $5
    $post(new CreateJournalEntryData('2026-02-10', [
        new JournalLineData($salesRet->id, PostingSide::DEBIT, 1000, costCenterId: $deptSales->id),
        new JournalLineData($cash->id, PostingSide::CREDIT, 1000),
    ]));
    $post(new CreateJournalEntryData('2026-02-10', [
        new JournalLineData($rent->id, PostingSide::DEBIT, 2000, costCenterId: $deptOps->id),
        new JournalLineData($vatOut->id, PostingSide::DEBIT, 300, taxId: $vat15->id),
        new JournalLineData($cash->id, PostingSide::CREDIT, 2300),
    ]));
    $post(new CreateJournalEntryData('2026-02-28', [
        new JournalLineData($deprExp->id, PostingSide::DEBIT, 500, costCenterId: $deptOps->id),
        new JournalLineData($accumDepr->id, PostingSide::CREDIT, 500),
    ]));

    // Mar 15: Second Credit Sale to Customer #1: $200, due Mar 25
    $post(new CreateJournalEntryData('2026-03-15', [
        new JournalLineData($ar->id, PostingSide::DEBIT, 20000, partyType: 'customer', partyId: 1, dueDate: '2026-03-25'),
        new JournalLineData($rev->id, PostingSide::CREDIT, 20000, costCenterId: $deptSales->id),
    ]));

    // 1. Trial Balance for February ONLY (Opening = Jan balances, Period = Feb movements, Ending = Jan+Feb)
    $tbFeb = ium()->accounting()->reports()->trialBalance(fromDate: '2026-02-01', toDate: '2026-02-28');
    expect($tbFeb->isBalanced)->toBeTrue()
        ->and($tbFeb->totalOpeningDebit)->toBe(161500)
        ->and($tbFeb->totalOpeningCredit)->toBe(161500)
        ->and($tbFeb->totalPeriodDebit)->toBe(3800)
        ->and($tbFeb->totalPeriodCredit)->toBe(3800)
        ->and($tbFeb->totalEndingDebit)->toBe($tbFeb->totalEndingCredit);

    // 2. Point-in-Time Balance Sheet as of Feb 28 (excludes Mar 15 sale & subtracts $5 contra-asset Accum Depr!)
    $bsFeb = ium()->accounting()->reports()->balanceSheet('2026-02-28');
    expect($bsFeb->isBalanced)->toBeTrue()
        ->and($bsFeb->discrepancy)->toBe(0)
        // Assets: Cash (100000-1000-2300=96700) + AR (11500) + Equip (50000) - AccumDepr (500) = 157700
        ->and($bsFeb->totalAssets)->toBe(157700)
        // Liab: VAT (1500-300=1200), Equity: Capital (150000) + NetIncome (10000-1000-2000-500=6500) = 157700
        ->and($bsFeb->totalLiabilities)->toBe(1200)
        ->and($bsFeb->currentEarnings)->toBe(6500);

    // 3. Income Statement filtered by Cost Center (SALES vs OPS)
    $pnlSales = ium()->accounting()->reports()->incomeStatement(fromDate: '2026-01-01', toDate: '2026-03-31', costCenterId: $deptSales->id);
    $pnlOps = ium()->accounting()->reports()->incomeStatement(fromDate: '2026-01-01', toDate: '2026-03-31', costCenterId: $deptOps->id);
    expect($pnlSales->totalRevenue)->toBe(29000) // 10000 + 20000 - 1000 contra sales return
        ->and($pnlSales->netIncome)->toBe(29000)
        ->and($pnlOps->totalExpense)->toBe(2500)
        ->and($pnlOps->netIncome)->toBe(-2500);

    // 4. Account Ledger for Cash in February with exact Opening Balance ($1,000.00 = 100000)
    $cashLedger = ium()->accounting()->reports()->accountLedger($cash->id, '2026-02-01', '2026-02-28');
    expect($cashLedger->openingBalance)->toBe(100000)
        ->and($cashLedger->periodCredit)->toBe(3300)
        ->and($cashLedger->closingBalance)->toBe(96700)
        ->and($cashLedger->entries)->toHaveCount(2);

    // 5. Partner Ledger for Customer #1
    $partner = ium()->accounting()->reports()->partnerLedger('customer', 1);
    expect($partner->closingBalance)->toBe(31500)
        ->and($partner->totalOpenResidual)->toBe(31500)
        ->and($partner->entries)->toHaveCount(2);

    // 6. AR Aging Report as of 2026-03-20:
    //    Inv1 (due Jan 20 -> 59 days overdue -> 31-60 bucket = 11500)
    //    Inv2 (due Mar 25 -> not yet due -> Current bucket = 20000)
    $aging = ium()->accounting()->reports()->aging($ar->id, '2026-03-20', 'customer');
    expect($aging->totalCurrent)->toBe(20000)
        ->and($aging->totalDays31To60)->toBe(11500)
        ->and($aging->grandTotal)->toBe(31500);

    // 7. VAT Return Q1 2026: Output 1500 - Input 300 = Net Payable 1200
    $vat = ium()->accounting()->reports()->vatReturn('2026-01-01', '2026-03-31');
    expect($vat->totalOutputVat)->toBe(1500)
        ->and($vat->totalInputVat)->toBe(300)
        ->and($vat->netPayable)->toBe(1200);
});
