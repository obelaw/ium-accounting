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
        ->and($approved->approver_id)->toBe('manager-bob')
        ->and($approved->isApproved())->toBeTrue();
});

test('rejected voucher records mandatory reason and can be re-submitted for approval', function () {
    \Illuminate\Support\Facades\Event::fake([\Obelaw\Ium\Accounting\Events\JournalEntryRejected::class]);

    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-04-10',
        'creator_id' => 'accountant-alice',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 15000],
            ['account_id' => $this->equity->id, 'type' => PostingSide::CREDIT, 'amount' => 15000],
        ],
    ]));

    $this->journalService->submit($entry->id, 'accountant-alice');

    // Empty rejection reason is rejected
    expect(fn() => $this->journalService->reject($entry->id, 'manager-bob', '   '))
        ->toThrow(InvalidArgumentException::class, 'Rejection reason is mandatory.');

    $rejected = $this->journalService->reject($entry->id, 'manager-bob', 'Missing supporting bank receipt');

    expect($rejected->status->value)->toBe('rejected')
        ->and($rejected->rejection_reason)->toBe('Missing supporting bank receipt');

    \Illuminate\Support\Facades\Event::assertDispatched(\Obelaw\Ium\Accounting\Events\JournalEntryRejected::class);

    // Re-submit rejected voucher clears rejection_reason
    $resubmitted = $this->journalService->submit($rejected->id, 'accountant-alice');
    expect($resubmitted->status->value)->toBe('pending_approval')
        ->and($resubmitted->rejection_reason)->toBeNull();
});

test('enforces valid journal entry lifecycle state transitions', function () {
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-04-15',
        'creator_id' => 'accountant-alice',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 10000],
            ['account_id' => $this->equity->id, 'type' => PostingSide::CREDIT, 'amount' => 10000],
        ],
    ]));

    // Cannot approve or reject a DRAFT directly without submitting first
    expect(fn() => $this->journalService->approve($entry->id, 'manager-bob'))
        ->toThrow(\Obelaw\Ium\Accounting\Exceptions\InvalidStateTransitionException::class);

    expect(fn() => $this->journalService->reject($entry->id, 'manager-bob', 'Invalid state'))
        ->toThrow(\Obelaw\Ium\Accounting\Exceptions\InvalidStateTransitionException::class);

    $posted = $this->journalService->post($entry->id, 'manager-bob');

    // Cannot submit an already POSTED entry
    expect(fn() => $this->journalService->submit($posted->id, 'accountant-alice'))
        ->toThrow(\Obelaw\Ium\Accounting\Exceptions\InvalidStateTransitionException::class);
});

test('allows self-approval when strict maker-checker config is disabled', function () {
    config()->set('ium.accounting.maker_checker.strict', false);

    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-04-20',
        'creator_id' => 'solo-accountant',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 20000],
            ['account_id' => $this->equity->id, 'type' => PostingSide::CREDIT, 'amount' => 20000],
        ],
    ]));

    $this->journalService->submit($entry->id, 'solo-accountant');
    $approved = $this->journalService->approve($entry->id, 'solo-accountant');

    expect($approved->isApproved())->toBeTrue()
        ->and($approved->approver_id)->toBe('solo-accountant');
});
