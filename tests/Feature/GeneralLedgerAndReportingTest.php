<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;
use Obelaw\Ium\Accounting\Services\LedgerService;

beforeEach(function () {
    $this->accountService = app(AccountService::class);
    $this->journalService = app(JournalEntryService::class);
    $this->ledgerService = app(LedgerService::class);

    // Setup accounts
    $this->cash = $this->accountService->create(CreateAccountDto::from([
        'code' => '1010',
        'name' => 'Cash',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->ar = $this->accountService->create(CreateAccountDto::from([
        'code' => '1200',
        'name' => 'Accounts Receivable',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->ap = $this->accountService->create(CreateAccountDto::from([
        'code' => '2010',
        'name' => 'Accounts Payable',
        'type' => AccountType::LIABILITY,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->capital = $this->accountService->create(CreateAccountDto::from([
        'code' => '3010',
        'name' => 'Paid-in Capital',
        'type' => AccountType::EQUITY,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->revenue = $this->accountService->create(CreateAccountDto::from([
        'code' => '4010',
        'name' => 'Consulting Revenue',
        'type' => AccountType::REVENUE,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->expense = $this->accountService->create(CreateAccountDto::from([
        'code' => '5010',
        'name' => 'Office Supplies Expense',
        'type' => AccountType::EXPENSE,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));
});

test('trial balance maintains debit-credit parity across multiple posted transactions', function () {
    // 1. Initial Investment: Cash 10,000 / Capital 10,000
    $jv1 = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-05-01',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 10000.0],
            ['account_id' => $this->capital->id, 'type' => PostingSide::CREDIT, 'amount' => 10000.0],
        ],
    ]));
    $this->journalService->post($jv1->id, 'sys');

    // 2. Service Rendered on Credit: AR 3,500 / Revenue 3,500
    $jv2 = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-05-05',
        'lines' => [
            ['account_id' => $this->ar->id, 'type' => PostingSide::DEBIT, 'amount' => 3500.0],
            ['account_id' => $this->revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 3500.0],
        ],
    ]));
    $this->journalService->post($jv2->id, 'sys');

    // 3. Purchase Supplies on Credit: Expense 800 / AP 800
    $jv3 = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-05-10',
        'lines' => [
            ['account_id' => $this->expense->id, 'type' => PostingSide::DEBIT, 'amount' => 800.0],
            ['account_id' => $this->ap->id, 'type' => PostingSide::CREDIT, 'amount' => 800.0],
        ],
    ]));
    $this->journalService->post($jv3->id, 'sys');

    // Generate Trial Balance
    $tb = $this->ledgerService->generateTrialBalance();

    expect($tb['is_balanced'])->toBeTrue()
        ->and($tb['total_debit'])->toBe(14300)
        ->and($tb['total_credit'])->toBe(14300)
        ->and($tb['imbalance'])->toBe(0);

    // Generate Income Statement
    $pnl = $this->ledgerService->generateIncomeStatement();
    expect($pnl['revenues']['total'])->toBe(3500)
        ->and($pnl['expenses']['total'])->toBe(800)
        ->and($pnl['net_income'])->toBe(2700)
        ->and($pnl['is_profitable'])->toBeTrue();

    // Generate Balance Sheet (Assets = Liabilities + Equity + Interim Net Income)
    $bs = $this->ledgerService->generateBalanceSheet();
    expect($bs['is_balanced'])->toBeTrue()
        ->and($bs['discrepancy'])->toBe(0)
        ->and($bs['assets']['total'])->toBe(13500)
        ->and($bs['liabilities']['total'])->toBe(800)
        ->and($bs['equity']['total'])->toBe(10000)
        ->and($bs['equity']['current_year_earnings'])->toBe(2700)
        ->and($bs['total_liabilities_and_equity'])->toBe(13500);
});

