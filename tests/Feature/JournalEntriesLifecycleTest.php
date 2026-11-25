<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Obelaw\Ium\Accounting\Data\CreateAccountData;
use Obelaw\Ium\Accounting\Data\CreateCurrencyData;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Data\UpdateJournalEntryData;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException;
use Obelaw\Ium\Accounting\Exceptions\InvalidStateTransitionException;
use Obelaw\Ium\Accounting\Exceptions\MakerCheckerViolationException;
use Obelaw\Ium\Accounting\Exceptions\UnbalancedJournalEntryException;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Models\JournalEntryLine;
use Obelaw\Ium\Contracts\Actor;
use Obelaw\Ium\Contracts\Authorizer;

it('supports draft editing and deletion, rejects unbalanced entries, and enforces strict Maker-Checker and immutability', function () {
    $cash = ium()->accounting()->accounts()->create(new CreateAccountData('1010', 'Cash', AccountType::ASSET));
    $rev = ium()->accounting()->accounts()->create(new CreateAccountData('4010', 'Sales', AccountType::REVENUE));

    // Create unbalanced draft -> fails on submit
    $draft = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
        entryDate: '2026-04-01',
        narration: 'Initial draft',
        creatorId: 'alice',
        lines: [
            new JournalLineData($cash->id, PostingSide::DEBIT, 10000),
            new JournalLineData($rev->id, PostingSide::CREDIT, 9999),
        ],
    ));

    expect(fn () => ium()->accounting()->journalEntries()->submit($draft->id, 'alice'))
        ->toThrow(UnbalancedJournalEntryException::class);

    // Fix draft via updateDraft()
    $updated = ium()->accounting()->journalEntries()->updateDraft(new UpdateJournalEntryData(
        entryId: $draft->id,
        narration: 'Corrected draft',
        lines: [
            JournalLineData::fromMajor($cash->id, PostingSide::DEBIT, '100.00', 'USD'),
            JournalLineData::fromMajor($rev->id, PostingSide::CREDIT, '100.00', 'USD'),
        ],
    ));

    expect($updated->narration)->toBe('Corrected draft')
        ->and($updated->total_debit)->toBe(10000)
        ->and($updated->total_credit)->toBe(10000);

    // Cannot post DRAFT directly when require_approval=true
    expect(fn () => ium()->accounting()->journalEntries()->post($updated->id, 'bob'))
        ->toThrow(InvalidStateTransitionException::class);

    // Submit -> Reject -> Edit -> Resubmit -> Self-approve blocked -> Bob approves -> Post
    ium()->accounting()->journalEntries()->submit($updated->id, 'alice');
    $rejected = ium()->accounting()->journalEntries()->reject($updated->id, 'Need invoice ref', 'bob');
    expect($rejected->status)->toBe(JournalEntryStatus::REJECTED);

    ium()->accounting()->journalEntries()->updateDraft(new UpdateJournalEntryData($rejected->id, reference: 'INV-42'));
    ium()->accounting()->journalEntries()->submit($updated->id, 'alice');

    expect(fn () => ium()->accounting()->journalEntries()->approve($updated->id, 'alice'))
        ->toThrow(MakerCheckerViolationException::class);

    ium()->accounting()->journalEntries()->approve($updated->id, 'bob');
    $posted = ium()->accounting()->journalEntries()->post($updated->id, 'bob');

    expect($posted->isPosted())->toBeTrue()
        ->and($cash->fresh()->current_balance)->toBe(10000)
        ->and($rev->fresh()->current_balance)->toBe(10000);

    // Immutability checks: entry, lines, and GL postings are locked
    expect(fn () => $posted->update(['status' => JournalEntryStatus::DRAFT]))->toThrow(ImmutableJournalEntryException::class);
    expect(fn () => $posted->delete())->toThrow(ImmutableJournalEntryException::class);
    expect(fn () => $posted->lines->first()->update(['amount' => 1]))->toThrow(ImmutableJournalEntryException::class);
    expect(fn () => $posted->lines->first()->delete())->toThrow(ImmutableJournalEntryException::class);
    expect(fn () => GeneralLedgerPosting::first()->update(['debit' => 0]))->toThrow(ImmutableJournalEntryException::class);

    // Storno reversal zeroes balances
    $reversal = ium()->accounting()->journalEntries()->reverse($posted->id, 'Wrong customer', 'controller');
    expect($reversal->isPosted())->toBeTrue()
        ->and($posted->fresh()->status)->toBe(JournalEntryStatus::REVERSED)
        ->and($cash->fresh()->current_balance)->toBe(0)
        ->and($rev->fresh()->current_balance)->toBe(0);

    // Mutable draft can be deleted via deleteDraft()
    $temp = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
        entryDate: '2026-04-02',
        lines: [
            new JournalLineData($cash->id, PostingSide::DEBIT, 500),
            new JournalLineData($rev->id, PostingSide::CREDIT, 500),
        ],
    ));
    expect(ium()->accounting()->journalEntries()->deleteDraft($temp->id))->toBeTrue()
        ->and(JournalEntry::find($temp->id))->toBeNull();
});

it('allocates multi-line FX functional amounts with zero rounding drift and honors Authorizer', function () {
    ium()->accounting()->currencies()->base(); // USD
    ium()->accounting()->currencies()->create(new CreateCurrencyData('EUR', 'Euro', '€', exchangeRate: 1.115));

    $e1 = ium()->accounting()->accounts()->create(new CreateAccountData('5010', 'Exp 1', AccountType::EXPENSE));
    $e2 = ium()->accounting()->accounts()->create(new CreateAccountData('5020', 'Exp 2', AccountType::EXPENSE));
    $e3 = ium()->accounting()->accounts()->create(new CreateAccountData('5030', 'Exp 3', AccountType::EXPENSE));
    $cash = ium()->accounting()->accounts()->create(new CreateAccountData('1010', 'Cash', AccountType::ASSET));

    // 3 debit lines x 100 EUR cents = 300 EUR cents @ 1.115 = 335 USD cents (distributed [112, 112, 111])
    $entry = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
        entryDate: '2026-05-01',
        currency: 'EUR',
        exchangeRate: 1.115,
        lines: [
            new JournalLineData($e1->id, PostingSide::DEBIT, 100),
            new JournalLineData($e2->id, PostingSide::DEBIT, 100),
            new JournalLineData($e3->id, PostingSide::DEBIT, 100),
            new JournalLineData($cash->id, PostingSide::CREDIT, 300),
        ],
    ));

    $posted = ium()->accounting()->journalEntries()->post($entry->id, 'sys', bypassApproval: true);
    $tb = ium()->accounting()->reports()->trialBalance();

    expect($posted->isPosted())->toBeTrue()
        ->and($tb->isBalanced)->toBeTrue()
        ->and($tb->totalEndingDebit)->toBe(335)
        ->and($tb->totalEndingCredit)->toBe(335);

    // Authorizer blocks unauthorized actions
    app()->instance(Authorizer::class, new class implements Authorizer {
        public function allows(string $ability, mixed $subject = null, ?Actor $actor = null): bool
        {
            return false;
        }

        public function authorize(string $ability, mixed $subject = null, ?Actor $actor = null): void
        {
            throw new AuthorizationException("Forbidden: {$ability}");
        }
    });

    expect(fn () => ium()->accounting()->accounts()->create(new CreateAccountData('9999', 'Blocked', AccountType::ASSET)))
        ->toThrow(AuthorizationException::class, 'Forbidden: accounting.account.create');
});
