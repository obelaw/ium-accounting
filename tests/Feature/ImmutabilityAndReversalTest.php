<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException;
use Obelaw\Ium\Accounting\Exceptions\ReversalNotAllowedException;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;

beforeEach(function () {
    $this->accountService = app(AccountService::class);
    $this->journalService = app(JournalEntryService::class);

    $this->bank = $this->accountService->create(CreateAccountDto::from([
        'code' => '1020',
        'name' => 'Bank Account',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->rent = $this->accountService->create(CreateAccountDto::from([
        'code' => '5010',
        'name' => 'Rent Expense',
        'type' => AccountType::EXPENSE,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));
});

test('posted journal entries cannot be edited or deleted in-place', function () {
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-03-01',
        'creator_id' => 'maker-1',
        'lines' => [
            ['account_id' => $this->rent->id, 'type' => PostingSide::DEBIT, 'amount' => 2500.0],
            ['account_id' => $this->bank->id, 'type' => PostingSide::CREDIT, 'amount' => 2500.0],
        ],
    ]));

    $this->journalService->submit($entry->id, 'maker-1');
    $this->journalService->approve($entry->id, 'checker-1');
    $posted = $this->journalService->post($entry->id, 'poster-1');

    // Attempting in-place modification must throw ImmutableJournalEntryException
    expect(fn() => $posted->update(['narration' => 'Unauthorized alteration']))
        ->toThrow(ImmutableJournalEntryException::class);

    // Attempting deletion must throw ImmutableJournalEntryException
    expect(fn() => $posted->delete())
        ->toThrow(ImmutableJournalEntryException::class);
});

test('reversing a posted voucher executes Storno correction and nets out balances', function () {
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-03-01',
        'creator_id' => 'maker-1',
        'lines' => [
            ['account_id' => $this->rent->id, 'type' => PostingSide::DEBIT, 'amount' => 3000.0],
            ['account_id' => $this->bank->id, 'type' => PostingSide::CREDIT, 'amount' => 3000.0],
        ],
    ]));

    $this->journalService->submit($entry->id, 'maker-1');
    $this->journalService->approve($entry->id, 'checker-1');
    $posted = $this->journalService->post($entry->id, 'poster-1');

    expect($this->bank->fresh()->current_balance)->toBe(-3000)
        ->and($this->rent->fresh()->current_balance)->toBe(3000);

    // Perform reversal
    $reversal = $this->journalService->reverse(
        $posted->id,
        'supervisor-1',
        'Duplicate invoice payment discovered'
    );

    expect($reversal->isPosted())->toBeTrue()
        ->and($posted->fresh()->status->value)->toBe('reversed')
        ->and($posted->fresh()->reversal_entry_id)->toBe($reversal->id)
        ->and($reversal->reversed_entry_id)->toBe($posted->id);

    // Ledger balances net back to 0
    expect($this->bank->fresh()->current_balance)->toBe(0)
        ->and($this->rent->fresh()->current_balance)->toBe(0);
});
