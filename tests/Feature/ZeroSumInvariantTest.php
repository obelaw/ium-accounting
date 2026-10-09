<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\UnbalancedJournalEntryException;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;

beforeEach(function () {
    $this->accountService = app(AccountService::class);
    $this->journalService = app(JournalEntryService::class);

    $this->cash = $this->accountService->create(CreateAccountDto::from([
        'code' => '1010',
        'name' => 'Cash on Hand',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->sales = $this->accountService->create(CreateAccountDto::from([
        'code' => '4010',
        'name' => 'Service Revenue',
        'type' => AccountType::REVENUE,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));
});

test('submitting or posting an unbalanced journal voucher throws UnbalancedJournalEntryException', function () {
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-02-01',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 1000.0],
            ['account_id' => $this->sales->id, 'type' => PostingSide::CREDIT, 'amount' => 950.0], // Unbalanced!
        ],
    ]));

    expect(fn() => $this->journalService->submit($entry->id, 'user-1'))
        ->toThrow(UnbalancedJournalEntryException::class);

    expect(fn() => $this->journalService->post($entry->id, 'user-1'))
        ->toThrow(UnbalancedJournalEntryException::class);
});

test('balanced journal vouchers pass validation and update ledger', function () {
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-02-01',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 1000.0],
            ['account_id' => $this->sales->id, 'type' => PostingSide::CREDIT, 'amount' => 1000.0],
        ],
    ]));

    $submitted = $this->journalService->submit($entry->id, 'user-1');
    expect($submitted->status->value)->toBe('pending_approval');

    $approved = $this->journalService->approve($submitted->id, 'checker-1');
    expect($approved->status->value)->toBe('approved');

    $posted = $this->journalService->post($approved->id, 'poster-1');
    expect($posted->status->value)->toBe('posted')
        ->and($posted->isPosted())->toBeTrue();
});
