<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountData;
use Obelaw\Ium\Accounting\Data\CreateJournalData;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Data\UpdateAccountData;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\JournalType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\NonPostingAccountException;

it('manages hierarchical COA, promotes parent leaf accounts, and blocks non-posting entries', function () {
    $root = ium()->accounting()->accounts()->create(new CreateAccountData('1000', 'Assets', AccountType::ASSET, AccountClassification::CLASS_ROOT));
    $cashGroup = ium()->accounting()->accounts()->create(new CreateAccountData('1100', 'Cash', AccountType::ASSET, AccountClassification::POSTING, parentId: $root->id));
    expect($cashGroup->is_leaf)->toBeTrue();

    $pettyCash = ium()->accounting()->accounts()->create(new CreateAccountData('1101', 'Petty Cash', AccountType::ASSET, AccountClassification::POSTING, parentId: $cashGroup->id));
    expect($cashGroup->fresh()->is_leaf)->toBeFalse()
        ->and($pettyCash->allowsPostings())->toBeTrue();

    $rev = ium()->accounting()->accounts()->create(new CreateAccountData('4010', 'Sales', AccountType::REVENUE));

    expect(fn () => ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
        entryDate: '2026-01-10',
        lines: [
            new JournalLineData($root->id, PostingSide::DEBIT, 1000),
            new JournalLineData($rev->id, PostingSide::CREDIT, 1000),
        ],
    )))->toThrow(NonPostingAccountException::class);

    $updated = ium()->accounting()->accounts()->update(new UpdateAccountData($pettyCash->id, name: 'Vault Cash', isReconcilable: true));
    expect($updated->name)->toBe('Vault Cash')
        ->and($updated->is_reconcilable)->toBeTrue();
});

it('supports dedicated Journals with independent sequence numbering', function () {
    ium()->accounting()->journals()->seedDefaults();

    $cash = ium()->accounting()->accounts()->create(new CreateAccountData('1010', 'Cash', AccountType::ASSET));
    $ar = ium()->accounting()->accounts()->create(new CreateAccountData('1200', 'AR', AccountType::ASSET, isReconcilable: true));
    $rev = ium()->accounting()->accounts()->create(new CreateAccountData('4010', 'Revenue', AccountType::REVENUE));

    $sales1 = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
        entryDate: '2026-03-01',
        journalCode: 'SJ',
        lines: [
            new JournalLineData($ar->id, PostingSide::DEBIT, 10000),
            new JournalLineData($rev->id, PostingSide::CREDIT, 10000),
        ],
    ));

    $sales2 = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
        entryDate: '2026-03-02',
        journalCode: 'SJ',
        lines: [
            new JournalLineData($ar->id, PostingSide::DEBIT, 20000),
            new JournalLineData($rev->id, PostingSide::CREDIT, 20000),
        ],
    ));

    $bank1 = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
        entryDate: '2026-03-03',
        journalCode: 'BNK',
        lines: [
            new JournalLineData($cash->id, PostingSide::DEBIT, 10000),
            new JournalLineData($ar->id, PostingSide::CREDIT, 10000),
        ],
    ));

    expect($sales1->entry_number)->toBe('SJ-2026-000001')
        ->and($sales2->entry_number)->toBe('SJ-2026-000002')
        ->and($bank1->entry_number)->toBe('BNK-2026-000001')
        ->and($sales1->journal->type)->toBe(JournalType::SALES);

    $custom = ium()->accounting()->journals()->create(new CreateJournalData('PAY', 'Payroll Journal', JournalType::GENERAL, 'PAY'));
    expect($custom->code)->toBe('PAY');
});
