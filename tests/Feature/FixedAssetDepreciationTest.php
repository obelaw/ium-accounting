<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalPeriodDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalYearDto;
use Obelaw\Ium\Accounting\Data\CreateFixedAssetDto;
use Obelaw\Ium\Accounting\Data\UpdateFixedAssetDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\DepreciationScheduleStatus;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Enums\FixedAssetStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Exceptions\FiscalPeriodClosedException;
use Obelaw\Ium\Accounting\Exceptions\MakerCheckerViolationException;
use Obelaw\Ium\Accounting\Exceptions\NonPostingAccountException;

beforeEach(function () {
    // 1. Seed COA accounts for Fixed Assets
    $this->assetAccount = ium()->accounting()->accounts()->create(new CreateAccountDto(
        code: '1510',
        name: 'Computer & Server Equipment',
        type: AccountType::ASSET,
        classification: AccountClassification::POSTING,
        normalBalance: NormalBalance::DEBIT,
        isLeaf: true,
    ));

    $this->accumDeprAccount = ium()->accounting()->accounts()->create(new CreateAccountDto(
        code: '1519',
        name: 'Accumulated Depreciation - Equipment',
        type: AccountType::ASSET,
        classification: AccountClassification::POSTING,
        normalBalance: NormalBalance::DEBIT,
        isLeaf: true,
    ));

    $this->deprExpenseAccount = ium()->accounting()->accounts()->create(new CreateAccountDto(
        code: '5610',
        name: 'Depreciation Expense',
        type: AccountType::EXPENSE,
        classification: AccountClassification::POSTING,
        normalBalance: NormalBalance::DEBIT,
        isLeaf: true,
    ));

    // 2. Seed Fiscal Year 2026 & Q1 Monthly Periods
    $this->fiscalYear = ium()->accounting()->fiscalPeriods()->createYear(new CreateFiscalYearDto(
        name: 'FY 2026',
        startDate: '2026-01-01',
        endDate: '2026-12-31',
        status: FiscalPeriodStatus::OPEN,
    ));

    $this->janPeriod = ium()->accounting()->fiscalPeriods()->createPeriod(new CreateFiscalPeriodDto(
        fiscalYearId: $this->fiscalYear->id,
        periodNumber: 1,
        name: 'Jan 2026',
        startDate: '2026-01-01',
        endDate: '2026-01-31',
        status: FiscalPeriodStatus::OPEN,
    ));

    $this->febPeriod = ium()->accounting()->fiscalPeriods()->createPeriod(new CreateFiscalPeriodDto(
        fiscalYearId: $this->fiscalYear->id,
        periodNumber: 2,
        name: 'Feb 2026',
        startDate: '2026-02-01',
        endDate: '2026-02-28',
        status: FiscalPeriodStatus::OPEN,
    ));

    $this->marPeriod = ium()->accounting()->fiscalPeriods()->createPeriod(new CreateFiscalPeriodDto(
        fiscalYearId: $this->fiscalYear->id,
        periodNumber: 3,
        name: 'Mar 2026',
        startDate: '2026-03-01',
        endDate: '2026-03-31',
        status: FiscalPeriodStatus::OPEN,
    ));
});

it('registers a fixed asset and generates straight-line schedule absorbing rounding remainders', function () {
    // $1,000.00 cost (100,000 cents), $0 salvage, 3 months useful life -> 33333 + 33333 + 33334 = 100,000
    $asset = ium()->accounting()->fixedAssets()->register(CreateFixedAssetDto::fromMajor([
        'asset_code' => 'FA-2026-001',
        'name' => 'Rack Server Cluster',
        'purchase_date' => '2026-01-05',
        'in_service_date' => '2026-01-05',
        'acquisition_cost' => 1000.00,
        'salvage_value' => 0.00,
        'useful_life_months' => 3,
        'asset_account_id' => $this->assetAccount->id,
        'accumulated_depreciation_account_id' => $this->accumDeprAccount->id,
        'depreciation_expense_account_id' => $this->deprExpenseAccount->id,
        'creator_id' => 'asset-accountant-1',
    ]));

    expect($asset->status)->toBe(FixedAssetStatus::ACTIVE)
        ->and($asset->acquisition_cost)->toBe(100000)
        ->and($asset->net_book_value)->toBe(100000)
        ->and($asset->schedules)->toHaveCount(3);

    $schedules = $asset->schedules;
    expect($schedules[0]->depreciation_amount)->toBe(33333)
        ->and($schedules[0]->net_book_value_after)->toBe(66667)
        ->and($schedules[1]->depreciation_amount)->toBe(33333)
        ->and($schedules[1]->net_book_value_after)->toBe(33334)
        ->and($schedules[2]->depreciation_amount)->toBe(33334)
        ->and($schedules[2]->accumulated_depreciation_after)->toBe(100000)
        ->and($schedules[2]->net_book_value_after)->toBe(0);
});

it('executes automated straight-line depreciation integrated with JournalEntryService and maintains zero-sum parity', function () {
    $asset = ium()->accounting()->fixedAssets()->register(new CreateFixedAssetDto(
        assetCode: 'FA-2026-002',
        name: 'Precision CNC Machine',
        purchaseDate: '2026-01-01',
        inServiceDate: '2026-01-01',
        acquisitionCost: 360000, // $3,600.00
        salvageValue: 60000,     // $600.00 -> Depreciable Basis = $3,000.00 (300,000 cents)
        usefulLifeMonths: 3,     // 100,000 cents ($1,000.00) per month
        assetAccountId: $this->assetAccount->id,
        accumulatedDepreciationAccountId: $this->accumDeprAccount->id,
        depreciationExpenseAccountId: $this->deprExpenseAccount->id,
        creatorId: 'maker-alice',
    ));

    // Run Jan, Feb, and Mar depreciation
    foreach ([$this->janPeriod, $this->febPeriod, $this->marPeriod] as $period) {
        $entries = ium()->accounting()->fixedAssets()->runDepreciationForPeriod(
            fiscalPeriodId: $period->id,
            makerId: 'maker-alice',
            checkerId: 'checker-bob',
            autoPost: true
        );

        expect($entries)->toHaveCount(1);
        $entry = $entries->first();

        expect($entry->status)->toBe(JournalEntryStatus::POSTED)
            ->and($entry->entry_type)->toBe(JournalEntryType::ADJUSTING)
            ->and($entry->total_debit)->toBe(100000)
            ->and($entry->total_credit)->toBe(100000);
    }

    $freshAsset = $asset->fresh(['schedules']);
    expect($freshAsset->accumulated_depreciation)->toBe(300000)
        ->and($freshAsset->net_book_value)->toBe(60000)
        ->and($freshAsset->status)->toBe(FixedAssetStatus::FULLY_DEPRECIATED)
        ->and($freshAsset->schedules->every(fn($s) => $s->status === DepreciationScheduleStatus::POSTED))->toBeTrue();

    // Verify General Ledger Trial Balance zero-sum parity
    $tb = ium()->accounting()->ledger()->generateTrialBalance();
    expect($tb['is_balanced'])->toBeTrue()
        ->and($tb['imbalance'])->toBe(0);
});

it('enforces fiscal period hard locks by throwing FiscalPeriodClosedException', function () {
    ium()->accounting()->fixedAssets()->register(new CreateFixedAssetDto(
        assetCode: 'FA-2026-003',
        name: 'Locked Period Asset',
        purchaseDate: '2026-01-10',
        inServiceDate: '2026-01-10',
        acquisitionCost: 120000,
        salvageValue: 0,
        usefulLifeMonths: 12,
        assetAccountId: $this->assetAccount->id,
        accumulatedDepreciationAccountId: $this->accumDeprAccount->id,
        depreciationExpenseAccountId: $this->deprExpenseAccount->id,
    ));

    // Hard-lock January 2026
    ium()->accounting()->fiscalPeriods()->lockPeriod($this->janPeriod->id, 'cfo-actor');

    expect(fn() => ium()->accounting()->fixedAssets()->runDepreciationForPeriod(
        fiscalPeriodId: $this->janPeriod->id,
        makerId: 'maker-alice',
        checkerId: 'checker-bob'
    ))->toThrow(FiscalPeriodClosedException::class);
});

it('enforces Maker-Checker segregation of duties during depreciation auto-posting', function () {
    ium()->accounting()->fixedAssets()->register(new CreateFixedAssetDto(
        assetCode: 'FA-2026-004',
        name: 'Maker Checker Asset',
        purchaseDate: '2026-01-10',
        inServiceDate: '2026-01-10',
        acquisitionCost: 120000,
        salvageValue: 0,
        usefulLifeMonths: 12,
        assetAccountId: $this->assetAccount->id,
        accumulatedDepreciationAccountId: $this->accumDeprAccount->id,
        depreciationExpenseAccountId: $this->deprExpenseAccount->id,
    ));

    expect(fn() => ium()->accounting()->fixedAssets()->runDepreciationForPeriod(
        fiscalPeriodId: $this->janPeriod->id,
        makerId: 'same-user-id',
        checkerId: 'same-user-id',
        autoPost: true
    ))->toThrow(MakerCheckerViolationException::class);
});

it('rejects non-posting aggregator accounts when registering a fixed asset', function () {
    $aggregator = ium()->accounting()->accounts()->create(new CreateAccountDto(
        code: '1500',
        name: 'Property, Plant & Equipment (Group)',
        type: AccountType::ASSET,
        classification: AccountClassification::MAIN_GROUP,
        normalBalance: NormalBalance::DEBIT,
        isLeaf: false,
    ));

    expect(fn() => ium()->accounting()->fixedAssets()->register(new CreateFixedAssetDto(
        assetCode: 'FA-2026-005',
        name: 'Invalid Account Asset',
        purchaseDate: '2026-01-10',
        inServiceDate: '2026-01-10',
        acquisitionCost: 100000,
        salvageValue: 0,
        usefulLifeMonths: 12,
        assetAccountId: $aggregator->id,
        accumulatedDepreciationAccountId: $this->accumDeprAccount->id,
        depreciationExpenseAccountId: $this->deprExpenseAccount->id,
    )))->toThrow(NonPostingAccountException::class);
});

it('updates fixed asset parameters and recalculates schedule before depreciation is posted', function () {
    $asset = ium()->accounting()->fixedAssets()->register(new CreateFixedAssetDto(
        assetCode: 'FA-2026-006',
        name: 'Delivery Van',
        purchaseDate: '2026-01-01',
        inServiceDate: '2026-01-01',
        acquisitionCost: 240000,
        salvageValue: 0,
        usefulLifeMonths: 12,
        assetAccountId: $this->assetAccount->id,
        accumulatedDepreciationAccountId: $this->accumDeprAccount->id,
        depreciationExpenseAccountId: $this->deprExpenseAccount->id,
    ));

    $updated = ium()->accounting()->fixedAssets()->update($asset->id, UpdateFixedAssetDto::from([
        'name' => 'Electric Delivery Van',
        'salvage_value' => 40000,
        'useful_life_months' => 4,
        'actor_id' => 'asset-manager',
    ]));

    expect($updated->name)->toBe('Electric Delivery Van')
        ->and($updated->schedules)->toHaveCount(4)
        ->and($updated->schedules->first()->depreciation_amount)->toBe(50000);
});
