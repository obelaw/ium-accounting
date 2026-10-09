<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalPeriodDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalYearDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\FiscalPeriodClosedException;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\FiscalPeriodService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;

beforeEach(function () {
    $this->accountService = app(AccountService::class);
    $this->periodService = app(FiscalPeriodService::class);
    $this->journalService = app(JournalEntryService::class);

    $this->cash = $this->accountService->create(CreateAccountDto::from([
        'code' => '1010',
        'name' => 'Cash',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->revenue = $this->accountService->create(CreateAccountDto::from([
        'code' => '4010',
        'name' => 'Sales',
        'type' => AccountType::REVENUE,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));
});

test('posting into a locked fiscal period is rejected by closed-period invariant', function () {
    $year = $this->periodService->createYear(CreateFiscalYearDto::from([
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]));

    $periodJan = $this->periodService->createPeriod(CreateFiscalPeriodDto::from([
        'fiscal_year_id' => $year->id,
        'period_number' => 1,
        'name' => 'January 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]));

    // Lock January 2026
    $this->periodService->lockPeriod($periodJan->id, 'auditor-1', 'Statutory audit closure');

    // Create entry targeting January 2026
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-01-15',
        'fiscal_period_id' => $periodJan->id,
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 1000.0],
            ['account_id' => $this->revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 1000.0],
        ],
    ]));

    // Posting attempt must throw FiscalPeriodClosedException
    expect(fn() => $this->journalService->post($entry->id, 'user-1'))
        ->toThrow(FiscalPeriodClosedException::class);

    // Reopen period
    $reopened = $this->periodService->reopenPeriod($periodJan->id, 'cfo-1', 'Late adjusting entry approved by Board');
    expect($reopened->isOpen())->toBeTrue();

    // Now posting succeeds
    $posted = $this->journalService->post($entry->id, 'user-1');
    expect($posted->isPosted())->toBeTrue();
});
