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

test('soft-closed period restricts regular postings but allows privileged accountant postings', function () {
    \Illuminate\Support\Facades\Event::fake([
        \Obelaw\Ium\Accounting\Events\FiscalPeriodClosed::class,
        \Obelaw\Ium\Accounting\Events\FiscalPeriodReopened::class,
    ]);

    $year = $this->periodService->createYear(CreateFiscalYearDto::from([
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]));

    $periodFeb = $this->periodService->createPeriod(CreateFiscalPeriodDto::from([
        'fiscal_year_id' => $year->id,
        'period_number' => 2,
        'name' => 'February 2026',
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
    ]));

    $softClosed = $this->periodService->softClosePeriod($periodFeb->id, 'controller-1', 'Preliminary month-end review');

    expect($softClosed->status)->toBe(FiscalPeriodStatus::SOFT_CLOSED)
        ->and($softClosed->isOpen())->toBeFalse()
        ->and($softClosed->isLocked())->toBeFalse();

    \Illuminate\Support\Facades\Event::assertDispatched(\Obelaw\Ium\Accounting\Events\FiscalPeriodClosed::class);

    // Regular accountant blocked
    expect(fn() => $this->periodService->assertPeriodAllowsPosting($softClosed, isPrivileged: false))
        ->toThrow(FiscalPeriodClosedException::class);

    // Privileged accountant permitted
    $this->periodService->assertPeriodAllowsPosting($softClosed, isPrivileged: true);

    // Reopening requires mandatory non-empty reason
    expect(fn() => $this->periodService->reopenPeriod($softClosed->id, 'cfo-1', '   '))
        ->toThrow(InvalidArgumentException::class, 'Reopening a closed fiscal period requires a mandatory reason.');

    $reopened = $this->periodService->reopenPeriod($softClosed->id, 'cfo-1', 'Approved adjustment window');
    expect($reopened->isOpen())->toBeTrue();

    \Illuminate\Support\Facades\Event::assertDispatched(\Obelaw\Ium\Accounting\Events\FiscalPeriodReopened::class);
});

test('automatically resolves fiscal period from transaction date and blocks submission into locked period', function () {
    $year = $this->periodService->createYear(CreateFiscalYearDto::from([
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]));

    $periodMar = $this->periodService->createPeriod(CreateFiscalPeriodDto::from([
        'fiscal_year_id' => $year->id,
        'period_number' => 3,
        'name' => 'March 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]));

    // Draft without explicit fiscal_period_id resolves March 2026 automatically
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-03-20',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 5000],
            ['account_id' => $this->revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 5000],
        ],
    ]));

    expect($entry->fiscal_period_id)->toBe($periodMar->id)
        ->and($this->periodService->findPeriod($periodMar->id)?->name)->toBe('March 2026');

    // Lock period before submission
    $this->periodService->lockPeriod($periodMar->id, 'auditor-1', 'Q1 hard lock');

    expect(fn() => $this->journalService->submit($entry->id, 'maker-1'))
        ->toThrow(FiscalPeriodClosedException::class);
});
