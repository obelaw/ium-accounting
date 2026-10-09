<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalPeriodDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalYearDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Data\FiscalYearCloseDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\ClosingService;
use Obelaw\Ium\Accounting\Services\FiscalPeriodService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;

beforeEach(function () {
    $this->accountService = app(AccountService::class);
    $this->journalService = app(JournalEntryService::class);
    $this->periodService = app(FiscalPeriodService::class);
    $this->closingService = app(ClosingService::class);

    $this->cash = $this->accountService->create(CreateAccountDto::from([
        'code' => '1010',
        'name' => 'Cash',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->retained = $this->accountService->create(CreateAccountDto::from([
        'code' => '3100',
        'name' => 'Retained Earnings',
        'type' => AccountType::EQUITY,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->summary = $this->accountService->create(CreateAccountDto::from([
        'code' => '3900',
        'name' => 'Income Summary',
        'type' => AccountType::EQUITY,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->revenue = $this->accountService->create(CreateAccountDto::from([
        'code' => '4010',
        'name' => 'Product Sales',
        'type' => AccountType::REVENUE,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->cogs = $this->accountService->create(CreateAccountDto::from([
        'code' => '5010',
        'name' => 'Cost of Goods Sold',
        'type' => AccountType::EXPENSE,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));
});

test('fiscal year-end closing zeroes out nominal accounts and transfers net profit to retained earnings', function () {
    $year = $this->periodService->createYear(CreateFiscalYearDto::from([
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]));

    $period = $this->periodService->createPeriod(CreateFiscalPeriodDto::from([
        'fiscal_year_id' => $year->id,
        'period_number' => 12,
        'name' => 'December 2026',
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-31',
    ]));

    // Record transactions during the year:
    // Revenue: 50,000
    $jv1 = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-06-01',
        'fiscal_period_id' => $period->id,
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 50000.0],
            ['account_id' => $this->revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 50000.0],
        ],
    ]));
    $this->journalService->post($jv1->id, 'sys');

    // Expense: 20,000
    $jv2 = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-07-01',
        'fiscal_period_id' => $period->id,
        'lines' => [
            ['account_id' => $this->cogs->id, 'type' => PostingSide::DEBIT, 'amount' => 20000.0],
            ['account_id' => $this->cash->id, 'type' => PostingSide::CREDIT, 'amount' => 20000.0],
        ],
    ]));
    $this->journalService->post($jv2->id, 'sys');

    expect($this->revenue->fresh()->current_balance)->toBe(50000.0)
        ->and($this->cogs->fresh()->current_balance)->toBe(20000.0)
        ->and($this->retained->fresh()->current_balance)->toBe(0.0);

    // Execute year-end close
    $closedYear = $this->closingService->closeFiscalYear(FiscalYearCloseDto::from([
        'fiscal_year_id' => $year->id,
        'actor_id' => 'controller-1',
        'notes' => 'Annual closing finalized',
    ]));

    expect($closedYear->isLocked())->toBeTrue()
        ->and($closedYear->closed_by)->toBe('controller-1');

    // Invariant: Nominal accounts are zeroed
    expect($this->revenue->fresh()->current_balance)->toBe(0.0)
        ->and($this->cogs->fresh()->current_balance)->toBe(0.0);

    // Invariant: Net Profit (50,000 - 20,000 = 30,000) transferred to Retained Earnings
    expect($this->retained->fresh()->current_balance)->toBe(30000.0);
});
