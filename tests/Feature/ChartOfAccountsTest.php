<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Data\JournalEntryLineDto;
use Obelaw\Ium\Accounting\Data\UpdateAccountDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
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

test('adding a child account to a leaf account automatically converts parent to non-leaf aggregator', function () {
    $parent = $this->accountService->create(CreateAccountDto::from([
        'code' => '1100',
        'name' => 'Cash Accounts',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    expect($parent->is_leaf)->toBeTrue();

    $child = $this->accountService->create(CreateAccountDto::from([
        'code' => '1101',
        'name' => 'Main Vault Cash',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'parent_id' => $parent->id,
        'is_leaf' => true,
    ]));

    expect($child->parent_id)->toBe($parent->id)
        ->and($parent->fresh()->is_leaf)->toBeFalse()
        ->and($parent->fresh()->allowsPostings())->toBeFalse();
});

test('enforces unique account codes and throws AccountNotFoundException for unknown accounts', function () {
    $this->accountService->create(CreateAccountDto::from([
        'code' => '1010',
        'name' => 'Cash',
        'type' => AccountType::ASSET,
    ]));

    expect(fn() => $this->accountService->create(CreateAccountDto::from([
        'code' => '1010',
        'name' => 'Duplicate Cash',
        'type' => AccountType::ASSET,
    ])))->toThrow(InvalidArgumentException::class, 'Account code "1010" already exists.');

    expect(fn() => $this->accountService->create(CreateAccountDto::from([
        'code' => '1020',
        'name' => 'Orphan Account',
        'type' => AccountType::ASSET,
        'parent_id' => 999999,
    ])))->toThrow(AccountNotFoundException::class);

    expect(fn() => $this->accountService->findOrFail(999999))
        ->toThrow(AccountNotFoundException::class);

    expect(fn() => $this->accountService->findByCodeOrFail('NON-EXISTENT'))
        ->toThrow(AccountNotFoundException::class);
});

test('can update account attributes and query posting accounts and scopes', function () {
    $root = $this->accountService->create(CreateAccountDto::from([
        'code' => '2000',
        'name' => 'Liabilities',
        'type' => AccountType::LIABILITY,
        'classification' => AccountClassification::CLASS_ROOT,
        'is_leaf' => false,
    ]));

    $ap = $this->accountService->create(CreateAccountDto::from([
        'code' => '2100',
        'name' => 'Accounts Payable',
        'type' => AccountType::LIABILITY,
        'classification' => AccountClassification::POSTING,
        'parent_id' => $root->id,
        'is_leaf' => true,
    ]));

    $updated = $this->accountService->update($ap->id, UpdateAccountDto::from([
        'name' => 'Trade Accounts Payable',
        'is_reconciliation' => true,
        'metadata' => ['category' => 'trade'],
    ]));

    expect($updated->name)->toBe('Trade Accounts Payable')
        ->and($updated->is_reconciliation)->toBeTrue()
        ->and($updated->metadata)->toBe(['category' => 'trade']);

    $postingAccounts = $this->accountService->postingAccounts();
    expect($postingAccounts)->toHaveCount(1)
        ->and($postingAccounts->first()->code)->toBe('2100');

    expect(Account::active()->count())->toBe(2)
        ->and(Account::ofType(AccountType::LIABILITY)->count())->toBe(2);
});

test('recalculates account balance accurately from general ledger postings', function () {
    $cash = $this->accountService->create(CreateAccountDto::from([
        'code' => '1010',
        'name' => 'Cash',
        'type' => AccountType::ASSET,
    ]));

    $revenue = $this->accountService->create(CreateAccountDto::from([
        'code' => '4010',
        'name' => 'Sales Revenue',
        'type' => AccountType::REVENUE,
    ]));

    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-02-10',
        'lines' => [
            ['account_id' => $cash->id, 'type' => PostingSide::DEBIT, 'amount' => 25000],
            ['account_id' => $revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 25000],
        ],
    ]));
    $this->journalService->post($entry->id, 'auditor-1');

    // Simulate cache drift on both debit-normal and credit-normal accounts
    $cash->update(['current_balance' => 0]);
    $revenue->update(['current_balance' => 999]);

    $recalculatedCash = $this->accountService->recalculateBalance($cash->id);
    $recalculatedRev = $this->accountService->recalculateBalance($revenue->id);

    expect($recalculatedCash)->toBe(25000)
        ->and($cash->fresh()->current_balance)->toBe(25000)
        ->and($recalculatedRev)->toBe(25000)
        ->and($revenue->fresh()->current_balance)->toBe(25000);
});
