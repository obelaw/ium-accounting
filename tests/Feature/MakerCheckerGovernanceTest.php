<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\MakerCheckerViolationException;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;

beforeEach(function () {
    $this->accountService = app(AccountService::class);
    $this->journalService = app(JournalEntryService::class);

    $this->cash = $this->accountService->create(CreateAccountDto::from([
        'code' => '1010',
        'name' => 'Cash',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->equity = $this->accountService->create(CreateAccountDto::from([
        'code' => '3000',
        'name' => 'Owner Capital',
        'type' => AccountType::EQUITY,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));
});

test('maker-checker separation enforces that approver cannot be the voucher creator', function () {
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-04-01',
        'creator_id' => 'accountant-alice',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 50000.0],
            ['account_id' => $this->equity->id, 'type' => PostingSide::CREDIT, 'amount' => 50000.0],
        ],
    ]));

    $this->journalService->submit($entry->id, 'accountant-alice');

    // Alice attempting to self-approve must be rejected
    expect(fn() => $this->journalService->approve($entry->id, 'accountant-alice'))
        ->toThrow(MakerCheckerViolationException::class);

    // Independent approver Bob succeeds
    $approved = $this->journalService->approve($entry->id, 'manager-bob');
    expect($approved->status->value)->toBe('approved')
        ->and($approved->approver_id)->toBe('manager-bob');
});
