<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateCurrencyDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Data\JournalEntryLineDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\UnbalancedJournalEntryException;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Models\JournalEntryLine;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\CurrencyService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;
use Obelaw\Ium\Accounting\Services\LedgerService;
use Obelaw\Ium\Accounting\ValueObjects\Money;

beforeEach(function () {
    $this->accountService = app(AccountService::class);
    $this->journalService = app(JournalEntryService::class);
    $this->ledgerService = app(LedgerService::class);
    $this->currencyService = app(CurrencyService::class);

    $this->cash = $this->accountService->create(CreateAccountDto::from([
        'code' => '1010',
        'name' => 'Cash on Hand',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->revenue = $this->accountService->create(CreateAccountDto::from([
        'code' => '4010',
        'name' => 'Service Revenue',
        'type' => AccountType::REVENUE,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->tax = $this->accountService->create(CreateAccountDto::from([
        'code' => '2020',
        'name' => 'Sales Tax Payable',
        'type' => AccountType::LIABILITY,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));
});

test('persists integer minor units in database tables without floating point loss', function () {
    // 150.75 USD = 15075 cents
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-03-01',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 15075],
            ['account_id' => $this->revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 15075],
        ],
    ]));

    expect($entry->total_debit)->toBe(15075)
        ->and($entry->total_credit)->toBe(15075);

    $this->journalService->post($entry->id, 'user-1');

    $lineDebit = JournalEntryLine::where('journal_entry_id', $entry->id)
        ->where('type', PostingSide::DEBIT->value)
        ->first();

    expect($lineDebit->amount)->toBe(15075)
        ->and($lineDebit->amount_functional)->toBe(15075);

    $posting = GeneralLedgerPosting::where('journal_entry_id', $entry->id)
        ->where('account_id', $this->cash->id)
        ->first();

    expect($posting->debit)->toBe(15075)
        ->and($posting->credit)->toBe(0)
        ->and($posting->balance_after)->toBe(15075);

    expect($this->cash->fresh()->current_balance)->toBe(15075);
});

test('enforces strict zero-sum down to exact single minor unit (1 cent imbalance rejected)', function () {
    // 10000 cents debited, but only 9999 cents credited (1 cent off)
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-03-01',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 10000],
            ['account_id' => $this->revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 9999],
        ],
    ]));

    expect(fn() => $this->journalService->submit($entry->id, 'user-1'))
        ->toThrow(UnbalancedJournalEntryException::class, 'Total debits (10000) do not equal total credits (9999). Imbalance is 1.');
});

test('multi-leg line items with odd cent distributions balance perfectly without float rounding errors', function () {
    // Total: 100.00 USD = 10000 cents split across 3 lines: 3333 + 3333 + 3334 = 10000
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-03-01',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 10000],
            ['account_id' => $this->revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 3333],
            ['account_id' => $this->revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 3333],
            ['account_id' => $this->tax->id, 'type' => PostingSide::CREDIT, 'amount' => 3334],
        ],
    ]));

    $posted = $this->journalService->post($entry->id, 'user-1');

    expect($posted->isPosted())->toBeTrue()
        ->and($posted->total_debit)->toBe(10000)
        ->and($posted->total_credit)->toBe(10000);

    $tb = $this->ledgerService->generateTrialBalance();
    expect($tb['is_balanced'])->toBeTrue()
        ->and($tb['total_debit'])->toBe(10000)
        ->and($tb['total_credit'])->toBe(10000)
        ->and($tb['imbalance'])->toBe(0);
});

test('converts decimal major input safely to minor units via DTO factory and is_major flag', function () {
    // Using fromMajor on CreateJournalEntryDto
    $dto = CreateJournalEntryDto::fromMajor([
        'entry_date' => '2026-03-01',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 125.50],
            ['account_id' => $this->revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 125.50],
        ],
    ]);

    expect($dto->lines[0]->amount)->toBe(12550)
        ->and($dto->lines[1]->amount)->toBe(12550);

    // Using JournalEntryLineDto::fromMajor directly
    $line = JournalEntryLineDto::fromMajor(
        accountId: $this->cash->id,
        type: PostingSide::DEBIT,
        amountMajor: 49.99
    );

    expect($line->amount)->toBe(4999)
        ->and($line->toMajor())->toBe(49.99);

    // Using amount_major in array data
    $lineFromArr = JournalEntryLineDto::from([
        'account_id' => $this->revenue->id,
        'type' => 'credit',
        'amount_major' => 49.99,
    ]);

    expect($lineFromArr->amount)->toBe(4999);
});

test('provides formatting helpers and presenters to display minor units back to major units', function () {
    $usd = $this->currencyService->getBaseCurrency();

    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-03-01',
        'lines' => [
            ['account_id' => $this->cash->id, 'type' => PostingSide::DEBIT, 'amount' => 125050], // $1,250.50
            ['account_id' => $this->revenue->id, 'type' => PostingSide::CREDIT, 'amount' => 125050],
        ],
    ]));
    $this->journalService->post($entry->id, 'user-1');

    // Journal Entry presenters
    expect($entry->totalDebitMajor())->toBe(1250.50)
        ->and($entry->totalCreditMajor())->toBe(1250.50)
        ->and($entry->formatTotalDebit($usd))->toBe('$ 1,250.50')
        ->and($entry->formatTotalCredit($usd))->toBe('$ 1,250.50');

    // Line item presenters
    $line = $entry->lines->first();
    expect($line->amountMajor())->toBe(1250.50)
        ->and($line->formatAmount($usd))->toBe('$ 1,250.50');

    // GL Posting presenters
    $posting = GeneralLedgerPosting::where('journal_entry_id', $entry->id)->first();
    expect($posting->debitMajor())->toBe(1250.50)
        ->and($posting->formatDebit($usd))->toBe('$ 1,250.50')
        ->and($posting->balanceAfterMajor())->toBe(1250.50)
        ->and($posting->formatBalanceAfter($usd))->toBe('$ 1,250.50');

    // Account presenters
    $cashAccount = $this->cash->fresh();
    expect($cashAccount->currentBalanceMajor())->toBe(1250.50)
        ->and($cashAccount->formatCurrentBalance($usd))->toBe('$ 1,250.50');

    // CurrencyService conversion and formatting helpers
    expect($this->currencyService->toSubUnit(12.50, 'USD'))->toBe(1250)
        ->and($this->currencyService->fromSubUnit(1250, 'USD'))->toBe(12.50)
        ->and($this->currencyService->formatMinor(1250, 'USD'))->toBe('$ 12.50')
        ->and($this->currencyService->formatSubUnit(50, 'USD', useSymbol: false))->toBe('50 Cents')
        ->and($this->currencyService->formatSubUnit(50, 'USD', useSymbol: true))->toBe('50 ¢')
        ->and($this->currencyService->formatMajorAndSubUnit(12.50, 'USD'))->toBe('$ 12 and 50 Cents');

    // Split units
    $split = $this->currencyService->splitUnits(12.50, 'USD');
    expect($split['major'])->toBe(12)
        ->and($split['minor'])->toBe(50);
});

test('Money value object supports minor unit instantiation and conversions', function () {
    $m = Money::fromMinor(1250, 'USD');

    expect($m->amount)->toBe(12.50)
        ->and($m->toMinorUnits())->toBe(1250)
        ->and($m->currency)->toBe('USD');
});
