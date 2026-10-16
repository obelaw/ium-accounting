<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\AccountingAuditLog;
use Obelaw\Ium\Accounting\Models\CostCenter;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\EventAccountMapping;
use Obelaw\Ium\Accounting\Models\ExchangeRate;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FiscalYear;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Models\JournalEntryLine;

test('All domain models apply the ium_accounting_ table prefix via ModelBase', function () {
    expect((new Account())->getTable())->toBe('ium_accounting_accounts')
        ->and((new AccountingAuditLog())->getTable())->toBe('ium_accounting_audit_logs')
        ->and((new CostCenter())->getTable())->toBe('ium_accounting_cost_centers')
        ->and((new Currency())->getTable())->toBe('ium_accounting_currencies')
        ->and((new EventAccountMapping())->getTable())->toBe('ium_accounting_event_account_mappings')
        ->and((new ExchangeRate())->getTable())->toBe('ium_accounting_exchange_rates')
        ->and((new FiscalPeriod())->getTable())->toBe('ium_accounting_fiscal_periods')
        ->and((new FiscalYear())->getTable())->toBe('ium_accounting_fiscal_years')
        ->and((new GeneralLedgerPosting())->getTable())->toBe('ium_accounting_gl_postings')
        ->and((new JournalEntry())->getTable())->toBe('ium_accounting_journal_entries')
        ->and((new JournalEntryLine())->getTable())->toBe('ium_accounting_journal_entry_lines');
});

test('Currency model formats major, minor, and sub-unit values across ISO currencies', function () {
    $usd = new Currency([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'precision' => 2,
        'symbol_first' => true,
        'decimal_mark' => '.',
        'thousands_separator' => ',',
        'is_base' => true,
    ]);

    $eurAfter = new Currency([
        'code' => 'EUR',
        'name' => 'Euro',
        'symbol' => '€',
        'precision' => 2,
        'symbol_first' => false,
        'decimal_mark' => ',',
        'thousands_separator' => '.',
        'is_base' => false,
    ]);

    expect($usd->isBase())->toBeTrue()
        ->and($eurAfter->isBase())->toBeFalse()
        ->and($usd->subUnitMultiplier())->toBe(100)
        ->and($usd->toMinorUnits(123.45))->toBe(12345)
        ->and($usd->toMajorUnits(12345))->toBe(123.45)
        ->and($usd->formatMinor(12345))->toBe('$ 123.45')
        ->and($eurAfter->format(1234.56))->toBe('1.234,56 €')
        ->and($usd->formatSubUnit(50, true))->toBe('50 ¢')
        ->and($usd->formatSubUnit(50, false))->toBe('50 Cents')
        ->and($usd->formatMajorAndSubUnit(12.50, false))->toBe('$ 12 and 50 Cents')
        ->and($eurAfter->formatMajorAndSubUnit(12.50, true))->toBe('12 € and 50 c')
        ->and($usd->splitUnits(12.50))->toBe([
            'major' => 12,
            'minor' => 50,
            'formatted' => '$ 12.50',
        ]);

    expect((new Currency(['code' => 'GBP']))->subUnitName())->toBe('Pence')
        ->and((new Currency(['code' => 'GBP']))->subUnitSymbol())->toBe('p')
        ->and((new Currency(['code' => 'EGP']))->subUnitName())->toBe('Piasters')
        ->and((new Currency(['code' => 'EGP']))->subUnitSymbol())->toBe('pt')
        ->and((new Currency(['code' => 'SAR']))->subUnitName())->toBe('Halalas')
        ->and((new Currency(['code' => 'KWD']))->subUnitName())->toBe('Fils')
        ->and((new Currency(['code' => 'XYZ']))->subUnitName())->toBe('Cents')
        ->and((new Currency(['code' => 'XYZ']))->subUnitSymbol())->toBe('¢');
});

test('Account model helper methods compute major balances, formatting, and posting eligibility', function () {
    $usd = new Currency([
        'code' => 'USD',
        'symbol' => '$',
        'precision' => 2,
        'symbol_first' => true,
        'decimal_mark' => '.',
        'thousands_separator' => ',',
    ]);

    $leaf = new Account([
        'code' => '1110',
        'name' => 'Cash',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'normal_balance' => NormalBalance::DEBIT,
        'is_leaf' => true,
        'current_balance' => 250075,
    ]);

    $group = new Account([
        'code' => '1100',
        'name' => 'Current Assets',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::SUB_GROUP,
        'normal_balance' => NormalBalance::DEBIT,
        'is_leaf' => false,
        'current_balance' => 0,
    ]);

    expect($leaf->allowsPostings())->toBeTrue()
        ->and($group->allowsPostings())->toBeFalse()
        ->and($leaf->currentBalanceMajor())->toBe(2500.75)
        ->and($leaf->formatCurrentBalance())->toBe('2,500.75')
        ->and($leaf->formatCurrentBalance($usd))->toBe('$ 2,500.75');
});

test('JournalEntry, JournalEntryLine, GeneralLedgerPosting, FiscalYear, and FiscalPeriod helpers work accurately', function () {
    $usd = new Currency([
        'code' => 'USD',
        'symbol' => '$',
        'precision' => 2,
        'symbol_first' => true,
        'decimal_mark' => '.',
        'thousands_separator' => ',',
    ]);

    $entry = new JournalEntry([
        'status' => JournalEntryStatus::DRAFT,
        'total_debit' => 150000,
        'total_credit' => 150000,
    ]);

    expect($entry->isDraft())->toBeTrue()
        ->and($entry->isApproved())->toBeFalse()
        ->and($entry->isPosted())->toBeFalse()
        ->and($entry->totalDebitMajor())->toBe(1500.0)
        ->and($entry->totalCreditMajor())->toBe(1500.0)
        ->and($entry->formatTotalDebit())->toBe('1,500.00')
        ->and($entry->formatTotalCredit($usd))->toBe('$ 1,500.00');

    $line = new JournalEntryLine([
        'type' => PostingSide::DEBIT,
        'amount' => 50025,
        'amount_functional' => 55028,
    ]);

    expect($line->isDebit())->toBeTrue()
        ->and($line->isCredit())->toBeFalse()
        ->and($line->amountMajor())->toBe(500.25)
        ->and($line->amountFunctionalMajor())->toBe(550.28)
        ->and($line->formatAmount())->toBe('500.25')
        ->and($line->formatAmount($usd))->toBe('$ 500.25')
        ->and($line->formatAmountFunctional())->toBe('550.28')
        ->and($line->formatAmountFunctional($usd))->toBe('$ 550.28');

    $lineWithoutFunctional = new JournalEntryLine([
        'type' => PostingSide::CREDIT,
        'amount' => 1000,
        'amount_functional' => null,
    ]);
    expect($lineWithoutFunctional->isCredit())->toBeTrue()
        ->and($lineWithoutFunctional->amountFunctionalMajor())->toBeNull()
        ->and($lineWithoutFunctional->formatAmountFunctional())->toBe('0.00');

    $posting = new GeneralLedgerPosting([
        'debit' => 10050,
        'credit' => 0,
        'balance_after' => 30075,
    ]);
    expect($posting->debitMajor())->toBe(100.5)
        ->and($posting->creditMajor())->toBe(0.0)
        ->and($posting->balanceAfterMajor())->toBe(300.75)
        ->and($posting->formatDebit())->toBe('100.50')
        ->and($posting->formatDebit($usd))->toBe('$ 100.50')
        ->and($posting->formatCredit())->toBe('0.00')
        ->and($posting->formatCredit($usd))->toBe('$ 0.00')
        ->and($posting->formatBalanceAfter())->toBe('300.75')
        ->and($posting->formatBalanceAfter($usd))->toBe('$ 300.75');

    $openYear = new FiscalYear(['status' => FiscalPeriodStatus::OPEN]);
    $lockedPeriod = new FiscalPeriod(['status' => FiscalPeriodStatus::LOCKED]);

    expect($openYear->isOpen())->toBeTrue()
        ->and($openYear->isLocked())->toBeFalse()
        ->and($lockedPeriod->isOpen())->toBeFalse()
        ->and($lockedPeriod->isLocked())->toBeTrue();
});
