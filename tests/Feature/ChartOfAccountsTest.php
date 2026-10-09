<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Data\JournalEntryLineDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\NonPostingAccountException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;

beforeEach(function () {
    $this->accountService = app(AccountService::class);
    $this->journalService = app(JournalEntryService::class);
});

test('can build a hierarchical Chart of Accounts tree', function () {
    // 1. Root Class: Assets
    $assets = $this->accountService->create(CreateAccountDto::from([
        'code' => '1000',
        'name' => 'Assets',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::CLASS_ROOT,
        'is_leaf' => false,
    ]));

    // 2. Main Group: Current Assets
    $currentAssets = $this->accountService->create(CreateAccountDto::from([
        'code' => '1100',
        'name' => 'Current Assets',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::MAIN_GROUP,
        'parent_id' => $assets->id,
        'is_leaf' => false,
    ]));

    // 3. Sub-Group: Cash and Equivalents
    $cashGroup = $this->accountService->create(CreateAccountDto::from([
        'code' => '1110',
        'name' => 'Cash & Cash Equivalents',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::SUB_GROUP,
        'parent_id' => $currentAssets->id,
        'is_leaf' => false,
    ]));

    // 4. Posting Account: Main Checking Bank Account
    $bankAccount = $this->accountService->create(CreateAccountDto::from([
        'code' => '1111',
        'name' => 'Main Operating Checking Account',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'parent_id' => $cashGroup->id,
        'is_leaf' => true,
    ]));

    expect($bankAccount->allowsPostings())->toBeTrue()
        ->and($cashGroup->allowsPostings())->toBeFalse()
        ->and($currentAssets->allowsPostings())->toBeFalse()
        ->and($assets->allowsPostings())->toBeFalse();

    $tree = $this->accountService->tree();
    expect($tree)->toHaveCount(1)
        ->and($tree->first()->code)->toBe('1000')
        ->and($tree->first()->children->first()->code)->toBe('1100');
});

test('posting to non-leaf aggregator accounts is strictly prohibited by domain law', function () {
    $assets = $this->accountService->create(CreateAccountDto::from([
        'code' => '1000',
        'name' => 'Assets',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::CLASS_ROOT,
        'is_leaf' => false,
    ]));

    $revenue = $this->accountService->create(CreateAccountDto::from([
        'code' => '4001',
        'name' => 'Sales Revenue',
        'type' => AccountType::REVENUE,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    expect(fn() => $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-01-15',
        'lines' => [
            ['account_id' => $assets->id, 'type' => PostingSide::DEBIT, 'amount' => 500.0],
            ['account_id' => $revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 500.0],
        ],
    ])))->toThrow(NonPostingAccountException::class);
});
