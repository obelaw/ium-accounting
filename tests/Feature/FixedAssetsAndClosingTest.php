<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CloseFiscalYearData;
use Obelaw\Ium\Accounting\Data\CreateAccountData;
use Obelaw\Ium\Accounting\Data\CreateFiscalYearData;
use Obelaw\Ium\Accounting\Data\CreateFixedAssetData;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\DisposeFixedAssetData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\DepreciationMethod;
use Obelaw\Ium\Accounting\Enums\FixedAssetStatus;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\FiscalPeriodClosedException;

it('manages Fixed Asset Straight-Line and Declining-Balance depreciation and posts automatic Gain/Loss on Disposal to GL', function () {
    $cash = ium()->accounting()->accounts()->create(new CreateAccountData('1010', 'Cash', AccountType::ASSET));
    $equip = ium()->accounting()->accounts()->create(new CreateAccountData('1510', 'Equipment', AccountType::ASSET));
    $accum = ium()->accounting()->accounts()->create(new CreateAccountData('1519', 'Accumulated Depr', AccountType::ASSET, normalBalance: NormalBalance::CREDIT));
    $deprExp = ium()->accounting()->accounts()->create(new CreateAccountData('5510', 'Depr Expense', AccountType::EXPENSE));
    $gainAcc = ium()->accounting()->accounts()->create(new CreateAccountData('4930', 'Gain on Disposal', AccountType::REVENUE));
    $lossAcc = ium()->accounting()->accounts()->create(new CreateAccountData('5930', 'Loss on Disposal', AccountType::EXPENSE));

    // Capitalize $120.00 equipment (12000 cents)
    $cap = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData('2026-01-01', [
        new JournalLineData($equip->id, PostingSide::DEBIT, 12000),
        new JournalLineData($cash->id, PostingSide::CREDIT, 12000),
    ]));
    ium()->accounting()->journalEntries()->post($cap->id, 'sys', bypassApproval: true);

    // Register DRAFT asset -> Activate -> Straight-line 3 months ($40/mo)
    $asset = ium()->accounting()->fixedAssets()->register(new CreateFixedAssetData(
        assetCode: 'FA-001',
        name: 'MacBook Pro',
        purchaseDate: '2026-01-01',
        inServiceDate: '2026-01-01',
        acquisitionCost: 12000,
        usefulLifeMonths: 3,
        assetAccountId: $equip->id,
        accumulatedDepreciationAccountId: $accum->id,
        depreciationExpenseAccountId: $deprExp->id,
        activateImmediately: false,
    ));

    expect($asset->status)->toBe(FixedAssetStatus::DRAFT)->and($asset->schedules)->toHaveCount(0);

    $asset = ium()->accounting()->fixedAssets()->activate($asset->id);
    expect($asset->status)->toBe(FixedAssetStatus::ACTIVE)->and($asset->schedules)->toHaveCount(3);

    // Depreciate Month 1 ($40 -> NBV = $80)
    ium()->accounting()->fixedAssets()->processScheduleLine($asset->schedules->first()->id, 'maker-1', 'checker-1');
    expect($asset->fresh()->accumulated_depreciation)->toBe(4000)
        ->and($asset->fresh()->net_book_value)->toBe(8000);

    // Dispose for $95.00 cash (9500) -> Gain = 9500 - 8000 = $15.00 (1500)!
    // GL entry: Dr Cash 9500, Dr AccumDepr 4000 / Cr Equip 12000, Cr Gain 1500 -> Balanced!
    $disposed = ium()->accounting()->fixedAssets()->dispose(new DisposeFixedAssetData(
        assetId: $asset->id,
        disposalDate: '2026-02-15',
        proceedsMinor: 9500,
        proceedsAccountId: $cash->id,
    ));

    expect($disposed->status)->toBe(FixedAssetStatus::DISPOSED)
        ->and($disposed->disposal_journal_entry_id)->not->toBeNull()
        ->and($equip->fresh()->current_balance)->toBe(0)
        ->and($accum->fresh()->current_balance)->toBe(0)
        ->and($gainAcc->fresh()->current_balance)->toBe(1500);

    // Declining-Balance schedule verifies total depreciation == depreciable basis
    $dbAsset = ium()->accounting()->fixedAssets()->register(new CreateFixedAssetData(
        assetCode: 'FA-002',
        name: 'CNC Machine',
        purchaseDate: '2026-01-01',
        inServiceDate: '2026-01-01',
        acquisitionCost: 100000,
        salvageValue: 10000,
        usefulLifeMonths: 12,
        assetAccountId: $equip->id,
        accumulatedDepreciationAccountId: $accum->id,
        depreciationExpenseAccountId: $deprExp->id,
        depreciationMethod: DepreciationMethod::DECLINING_BALANCE,
    ));

    expect($dbAsset->schedules)->toHaveCount(12)
        ->and($dbAsset->schedules->sum('depreciation_amount'))->toBe(90000)
        ->and($dbAsset->schedules->last()->net_book_value_after)->toBe(10000);
});

it('auto-generates 12 monthly fiscal periods, enforces period locks, and executes year-scoped closing with contra-revenue', function () {
    $fy2025 = ium()->accounting()->fiscalPeriods()->createYear(new CreateFiscalYearData('FY 2025', '2025-01-01', '2025-12-31', generateMonthlyPeriods: true));
    $fy2026 = ium()->accounting()->fiscalPeriods()->createYear(new CreateFiscalYearData('FY 2026', '2026-01-01', '2026-12-31', generateMonthlyPeriods: true));

    expect($fy2025->periods)->toHaveCount(12);

    $cash = ium()->accounting()->accounts()->create(new CreateAccountData('1010', 'Cash', AccountType::ASSET));
    $retained = ium()->accounting()->accounts()->create(new CreateAccountData('3100', 'Retained Earnings', AccountType::EQUITY));
    $rev = ium()->accounting()->accounts()->create(new CreateAccountData('4010', 'Sales', AccountType::REVENUE));
    $ret = ium()->accounting()->accounts()->create(new CreateAccountData('4090', 'Sales Returns', AccountType::REVENUE, normalBalance: NormalBalance::DEBIT));

    // FY2025: $500 sales - $50 returns = $450 profit
    $e1 = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData('2025-12-10', [
        new JournalLineData($cash->id, PostingSide::DEBIT, 50000),
        new JournalLineData($rev->id, PostingSide::CREDIT, 50000),
    ]));
    ium()->accounting()->journalEntries()->post($e1->id, 'sys', bypassApproval: true);

    $e2 = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData('2025-12-15', [
        new JournalLineData($ret->id, PostingSide::DEBIT, 5000),
        new JournalLineData($cash->id, PostingSide::CREDIT, 5000),
    ]));
    ium()->accounting()->journalEntries()->post($e2->id, 'sys', bypassApproval: true);

    // FY2026: $120 sales (must NOT be touched by FY2025 closing!)
    $e3 = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData('2026-01-10', [
        new JournalLineData($cash->id, PostingSide::DEBIT, 12000),
        new JournalLineData($rev->id, PostingSide::CREDIT, 12000),
    ]));
    ium()->accounting()->journalEntries()->post($e3->id, 'sys', bypassApproval: true);

    $closed = ium()->accounting()->closing()->closeFiscalYear(new CloseFiscalYearData($fy2025->id));
    expect($closed->isLocked())->toBeTrue()
        ->and($retained->fresh()->current_balance)->toBe(45000)
        ->and($ret->fresh()->current_balance)->toBe(0)
        ->and($rev->fresh()->current_balance)->toBe(12000);

    // Posting into locked FY2025 period is now rejected
    $late = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData('2025-12-20', [
        new JournalLineData($cash->id, PostingSide::DEBIT, 1000),
        new JournalLineData($rev->id, PostingSide::CREDIT, 1000),
    ]));

    expect(fn () => ium()->accounting()->journalEntries()->post($late->id, 'sys', bypassApproval: true))
        ->toThrow(FiscalPeriodClosedException::class);
});
