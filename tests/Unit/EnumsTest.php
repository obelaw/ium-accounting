<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\DimensionType;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;

test('AccountClassification enum enforces leaf posting rules', function () {
    expect(AccountClassification::CLASS_ROOT->value)->toBe('class')
        ->and(AccountClassification::MAIN_GROUP->value)->toBe('main_group')
        ->and(AccountClassification::SUB_GROUP->value)->toBe('sub_group')
        ->and(AccountClassification::POSTING->value)->toBe('posting')
        ->and(AccountClassification::CLASS_ROOT->allowsPostings())->toBeFalse()
        ->and(AccountClassification::MAIN_GROUP->allowsPostings())->toBeFalse()
        ->and(AccountClassification::SUB_GROUP->allowsPostings())->toBeFalse()
        ->and(AccountClassification::POSTING->allowsPostings())->toBeTrue();
});

test('AccountType maps accurately to normal balances and real vs nominal rules', function () {
    expect(AccountType::ASSET->normalBalance())->toBe(NormalBalance::DEBIT)
        ->and(AccountType::EXPENSE->normalBalance())->toBe(NormalBalance::DEBIT)
        ->and(AccountType::LIABILITY->normalBalance())->toBe(NormalBalance::CREDIT)
        ->and(AccountType::EQUITY->normalBalance())->toBe(NormalBalance::CREDIT)
        ->and(AccountType::REVENUE->normalBalance())->toBe(NormalBalance::CREDIT)
        ->and(AccountType::REVENUE->isNominal())->toBeTrue()
        ->and(AccountType::EXPENSE->isNominal())->toBeTrue()
        ->and(AccountType::ASSET->isNominal())->toBeFalse()
        ->and(AccountType::LIABILITY->isNominal())->toBeFalse()
        ->and(AccountType::EQUITY->isNominal())->toBeFalse()
        ->and(AccountType::ASSET->isReal())->toBeTrue()
        ->and(AccountType::LIABILITY->isReal())->toBeTrue()
        ->and(AccountType::EQUITY->isReal())->toBeTrue()
        ->and(AccountType::REVENUE->isReal())->toBeFalse()
        ->and(AccountType::EXPENSE->isReal())->toBeFalse();
});

test('PostingSide provides accurate opposite representation for Storno reversals', function () {
    expect(PostingSide::DEBIT->value)->toBe('debit')
        ->and(PostingSide::CREDIT->value)->toBe('credit')
        ->and(PostingSide::DEBIT->opposite())->toBe(PostingSide::CREDIT)
        ->and(PostingSide::CREDIT->opposite())->toBe(PostingSide::DEBIT);
});

test('NormalBalance enum exposes debit and credit cases', function () {
    expect(NormalBalance::DEBIT->value)->toBe('debit')
        ->and(NormalBalance::CREDIT->value)->toBe('credit');
});

test('JournalEntryStatus enum enforces mutability and posted state checks', function () {
    expect(JournalEntryStatus::DRAFT->isMutable())->toBeTrue()
        ->and(JournalEntryStatus::REJECTED->isMutable())->toBeTrue()
        ->and(JournalEntryStatus::PENDING_APPROVAL->isMutable())->toBeFalse()
        ->and(JournalEntryStatus::APPROVED->isMutable())->toBeFalse()
        ->and(JournalEntryStatus::POSTED->isMutable())->toBeFalse()
        ->and(JournalEntryStatus::REVERSED->isMutable())->toBeFalse()
        ->and(JournalEntryStatus::POSTED->isPosted())->toBeTrue()
        ->and(JournalEntryStatus::DRAFT->isPosted())->toBeFalse()
        ->and(JournalEntryStatus::PENDING_APPROVAL->isPosted())->toBeFalse()
        ->and(JournalEntryStatus::APPROVED->isPosted())->toBeFalse()
        ->and(JournalEntryStatus::REVERSED->isPosted())->toBeFalse()
        ->and(JournalEntryStatus::REJECTED->isPosted())->toBeFalse();
});

test('FiscalPeriodStatus enum enforces posting lock rules', function () {
    expect(FiscalPeriodStatus::OPEN->allowsPosting(false))->toBeTrue()
        ->and(FiscalPeriodStatus::OPEN->allowsPosting(true))->toBeTrue()
        ->and(FiscalPeriodStatus::SOFT_CLOSED->allowsPosting(false))->toBeFalse()
        ->and(FiscalPeriodStatus::SOFT_CLOSED->allowsPosting(true))->toBeTrue()
        ->and(FiscalPeriodStatus::LOCKED->allowsPosting(false))->toBeFalse()
        ->and(FiscalPeriodStatus::LOCKED->allowsPosting(true))->toBeFalse();
});

test('JournalEntryType, DimensionType, and AuditAction enums expose expected values', function () {
    expect(JournalEntryType::STANDARD->value)->toBe('standard')
        ->and(JournalEntryType::ADJUSTING->value)->toBe('adjusting')
        ->and(JournalEntryType::CLOSING->value)->toBe('closing')
        ->and(JournalEntryType::REVERSAL->value)->toBe('reversal')
        ->and(JournalEntryType::OPENING->value)->toBe('opening');

    expect(DimensionType::COST_CENTER->value)->toBe('cost_center')
        ->and(DimensionType::PROJECT->value)->toBe('project')
        ->and(DimensionType::DEPARTMENT->value)->toBe('department');

    expect(AuditAction::CREATED->value)->toBe('created')
        ->and(AuditAction::UPDATED->value)->toBe('updated')
        ->and(AuditAction::SUBMITTED->value)->toBe('submitted')
        ->and(AuditAction::APPROVED->value)->toBe('approved')
        ->and(AuditAction::REJECTED->value)->toBe('rejected')
        ->and(AuditAction::POSTED->value)->toBe('posted')
        ->and(AuditAction::REVERSED->value)->toBe('reversed')
        ->and(AuditAction::PERIOD_LOCKED->value)->toBe('period_locked')
        ->and(AuditAction::PERIOD_REOPENED->value)->toBe('period_reopened')
        ->and(AuditAction::YEAR_END_CLOSED->value)->toBe('year_end_closed');
});
