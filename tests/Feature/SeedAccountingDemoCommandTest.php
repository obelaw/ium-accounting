<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\CostCenter;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FiscalYear;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Services\LedgerService;

it('executes ium:accounting:demo-seed artisan command and establishes balanced ledger', function () {
    $this->artisan('ium:accounting:demo-seed')
        ->expectsOutputToContain('Obelawium Accounting (FMS) Demo Data Seeder')
        ->expectsOutputToContain('Seeding 4-Tier Hierarchical Chart of Accounts')
        ->expectsOutputToContain('PARITY (0 cents imbalance)')
        ->expectsOutputToContain('BALANCED (A = L + E)')
        ->assertSuccessful();

    // 1. Verify Currencies & Base Currency
    expect(Currency::count())->toBeGreaterThanOrEqual(7);
    $baseCurrency = Currency::where('is_base', true)->first();
    expect($baseCurrency)->not->toBeNull()
        ->and($baseCurrency->code)->toBe('USD');

    // 2. Verify Fiscal Year & Periods
    $fy2026 = FiscalYear::where('name', 'FY 2026')->first();
    expect($fy2026)->not->toBeNull()
        ->and(FiscalPeriod::where('fiscal_year_id', $fy2026->id)->count())->toBe(12);

    // 3. Verify Cost Centers & Projects
    expect(CostCenter::count())->toBe(6);
    expect(CostCenter::where('code', 'CC-ENG')->exists())->toBeTrue()
        ->and(CostCenter::where('code', 'PRJ-ALPHA')->exists())->toBeTrue();

    // 4. Verify Chart of Accounts Tree Hierarchy
    expect(Account::count())->toBeGreaterThanOrEqual(30);
    $bankUsd = Account::where('code', '1111')->first();
    expect($bankUsd)->not->toBeNull()
        ->and($bankUsd->is_leaf)->toBeTrue()
        ->and($bankUsd->parent)->not->toBeNull()
        ->and($bankUsd->current_balance)->toBeGreaterThan(0);

    // 5. Verify Vouchers & Lifecycle Statuses
    expect(JournalEntry::count())->toBe(11);
    expect(JournalEntry::where('status', JournalEntryStatus::POSTED)->count())->toBe(9)
        ->and(JournalEntry::where('status', JournalEntryStatus::PENDING_APPROVAL)->count())->toBe(1)
        ->and(JournalEntry::where('status', JournalEntryStatus::DRAFT)->count())->toBe(1);

    expect(GeneralLedgerPosting::count())->toBeGreaterThan(15);

    // 6. Verify Trial Balance Parity
    $ledgerService = app(LedgerService::class);
    $tb = $ledgerService->generateTrialBalance();

    expect($tb['is_balanced'])->toBeTrue()
        ->and($tb['imbalance'])->toBe(0)
        ->and($tb['total_debit'])->toBeGreaterThan(0)
        ->and($tb['total_debit'])->toBe($tb['total_credit']);

    // 7. Verify Balance Sheet Parity
    $bs = $ledgerService->generateBalanceSheet();
    expect($bs['is_balanced'])->toBeTrue()
        ->and($bs['assets']['total'])->toBe($bs['total_liabilities_and_equity']);
});

it('supports --fresh and --force flags to cleanly re-seed accounting data', function () {
    // Initial run
    $this->artisan('ium:accounting:demo-seed --force')->assertSuccessful();
    expect(JournalEntry::count())->toBe(11);

    // Re-seed with --fresh --force
    $this->artisan('ium:accounting:demo-seed --fresh --force')
        ->expectsOutputToContain('All ium_accounting_* tables truncated')
        ->assertSuccessful();

    expect(JournalEntry::count())->toBe(11);

    $ledgerService = app(LedgerService::class);
    $tb = $ledgerService->generateTrialBalance();
    expect($tb['is_balanced'])->toBeTrue()
        ->and($tb['imbalance'])->toBe(0);
});
