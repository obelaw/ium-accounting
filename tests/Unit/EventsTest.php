<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Events\AccountBalanceThresholdBreached;
use Obelaw\Ium\Accounting\Events\BaseCurrencyChanged;
use Obelaw\Ium\Accounting\Events\CurrencyCreated;
use Obelaw\Ium\Accounting\Events\ExchangeRateUpdated;
use Obelaw\Ium\Accounting\Events\FiscalPeriodClosed;
use Obelaw\Ium\Accounting\Events\FiscalPeriodReopened;
use Obelaw\Ium\Accounting\Events\FiscalYearClosed;
use Obelaw\Ium\Accounting\Events\JournalEntryApproved;
use Obelaw\Ium\Accounting\Events\JournalEntryCreated;
use Obelaw\Ium\Accounting\Events\JournalEntryPosted;
use Obelaw\Ium\Accounting\Events\JournalEntryRejected;
use Obelaw\Ium\Accounting\Events\JournalEntryReversed;
use Obelaw\Ium\Accounting\Events\JournalEntrySubmitted;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\ExchangeRate;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FiscalYear;
use Obelaw\Ium\Accounting\Models\JournalEntry;

test('Account, Currency, and Fiscal Period domain events hold expected payload properties', function () {
    $account = new Account(['code' => '1110', 'name' => 'Cash']);
    $breached = new AccountBalanceThresholdBreached($account, -500.0, 0.0);
    expect($breached->account)->toBe($account)
        ->and($breached->currentBalance)->toBe(-500.0)
        ->and($breached->threshold)->toBe(0.0);

    $usd = new Currency(['code' => 'USD']);
    $eur = new Currency(['code' => 'EUR']);
    $currencyCreated = new CurrencyCreated($eur, 'admin');
    expect($currencyCreated->currency)->toBe($eur)
        ->and($currencyCreated->actorId)->toBe('admin');

    $baseChanged = new BaseCurrencyChanged($usd, $eur, 'admin');
    expect($baseChanged->previousBase)->toBe($usd)
        ->and($baseChanged->newBase)->toBe($eur)
        ->and($baseChanged->actorId)->toBe('admin');

    $rate = new ExchangeRate(['currency_code' => 'EUR', 'rate' => 1.08]);
    $rateUpdated = new ExchangeRateUpdated($rate, 'fx-bot');
    expect($rateUpdated->exchangeRate)->toBe($rate)
        ->and($rateUpdated->actorId)->toBe('fx-bot');

    $period = new FiscalPeriod(['name' => 'Jan 2026']);
    $periodClosed = new FiscalPeriodClosed($period, 'cfo', 'Month-end close');
    $periodReopened = new FiscalPeriodReopened($period, 'cfo', 'Audit adjustment');
    expect($periodClosed->period)->toBe($period)
        ->and($periodClosed->actorId)->toBe('cfo')
        ->and($periodClosed->reason)->toBe('Month-end close')
        ->and($periodReopened->reason)->toBe('Audit adjustment');

    $year = new FiscalYear(['name' => 'FY 2026']);
    $closingEntry = new JournalEntry(['entry_number' => 'JE-CLOSE-2026']);
    $yearClosed = new FiscalYearClosed($year, $closingEntry, 'cfo');
    expect($yearClosed->fiscalYear)->toBe($year)
        ->and($yearClosed->closingEntry)->toBe($closingEntry)
        ->and($yearClosed->actorId)->toBe('cfo');
});

test('JournalEntry lifecycle domain events hold expected payload properties', function () {
    $entry = new JournalEntry(['entry_number' => 'JE-2026-0001']);
    $reversal = new JournalEntry(['entry_number' => 'JE-2026-0002']);

    $created = new JournalEntryCreated($entry, 'maker');
    $submitted = new JournalEntrySubmitted($entry, 'maker');
    $approved = new JournalEntryApproved($entry, 'checker');
    $rejected = new JournalEntryRejected($entry, 'checker', 'Invalid tax code');
    $posted = new JournalEntryPosted($entry, 'checker');
    $reversed = new JournalEntryReversed($entry, $reversal, 'auditor', 'Storno correction');

    expect($created->entry)->toBe($entry)->and($created->actorId)->toBe('maker')
        ->and($submitted->entry)->toBe($entry)->and($submitted->actorId)->toBe('maker')
        ->and($approved->entry)->toBe($entry)->and($approved->approverId)->toBe('checker')
        ->and($rejected->entry)->toBe($entry)->and($rejected->reason)->toBe('Invalid tax code')
        ->and($posted->entry)->toBe($entry)->and($posted->actorId)->toBe('checker')
        ->and($reversed->originalEntry)->toBe($entry)
        ->and($reversed->reversalEntry)->toBe($reversal)
        ->and($reversed->actorId)->toBe('auditor')
        ->and($reversed->reason)->toBe('Storno correction');
});
