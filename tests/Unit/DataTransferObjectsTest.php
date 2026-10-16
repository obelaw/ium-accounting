<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\AclIngestEventDto;
use Obelaw\Ium\Accounting\Data\ApproveJournalEntryDto;
use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateCostCenterDto;
use Obelaw\Ium\Accounting\Data\CreateCurrencyDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalPeriodDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalYearDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Data\CurrencyConversionResultDto;
use Obelaw\Ium\Accounting\Data\FiscalYearCloseDto;
use Obelaw\Ium\Accounting\Data\JournalEntryLineDto;
use Obelaw\Ium\Accounting\Data\PostJournalEntryDto;
use Obelaw\Ium\Accounting\Data\RejectJournalEntryDto;
use Obelaw\Ium\Accounting\Data\ReverseJournalEntryDto;
use Obelaw\Ium\Accounting\Data\SetExchangeRateDto;
use Obelaw\Ium\Accounting\Data\SubmitJournalEntryDto;
use Obelaw\Ium\Accounting\Data\UpdateAccountDto;
use Obelaw\Ium\Accounting\Data\UpdateCurrencyDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\DimensionType;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;

test('CreateAccountDto and UpdateAccountDto parse arrays, defaults, and serialize accurately', function () {
    $dto = CreateAccountDto::from([
        'code' => ' 1110 ',
        'name' => ' Cash on Hand ',
        'type' => 'asset',
        'classification' => 'posting',
        'currency' => 'eur',
        'parent_id' => 10,
        'is_reconciliation' => true,
        'actor_id' => 'admin',
        'metadata' => ['region' => 'EU'],
    ]);

    expect($dto->code)->toBe('1110')
        ->and($dto->name)->toBe('Cash on Hand')
        ->and($dto->type)->toBe(AccountType::ASSET)
        ->and($dto->classification)->toBe(AccountClassification::POSTING)
        ->and($dto->normalBalance)->toBe(NormalBalance::DEBIT)
        ->and($dto->isLeaf)->toBeTrue()
        ->and($dto->currency)->toBe('EUR')
        ->and($dto->toArray()['type'])->toBe('asset');

    expect(fn() => CreateAccountDto::from(['name' => 'Test', 'type' => 'asset']))
        ->toThrow(InvalidArgumentException::class, 'Account code is required.');
    expect(fn() => CreateAccountDto::from(['code' => '100', 'type' => 'asset']))
        ->toThrow(InvalidArgumentException::class, 'Account name is required.');
    expect(fn() => CreateAccountDto::from(['code' => '100', 'name' => 'Test']))
        ->toThrow(InvalidArgumentException::class, 'Account type is required.');

    $updateDto = UpdateAccountDto::from([
        'name' => ' Updated Cash ',
        'type' => AccountType::ASSET,
        'classification' => 'sub_group',
        'normal_balance' => 'debit',
        'is_active' => false,
    ]);

    expect($updateDto->name)->toBe('Updated Cash')
        ->and($updateDto->classification)->toBe(AccountClassification::SUB_GROUP)
        ->and($updateDto->toArray())->toBe([
            'name' => 'Updated Cash',
            'type' => 'asset',
            'classification' => 'sub_group',
            'normal_balance' => 'debit',
            'is_active' => false,
        ]);
});

test('CreateCostCenterDto parses arrays, defaults, and validates required fields', function () {
    $dto = CreateCostCenterDto::from([
        'code' => ' CC-100 ',
        'name' => ' R&D Department ',
        'dimension_type' => 'department',
        'parent_id' => 1,
        'actor_id' => 'user-1',
    ]);

    expect($dto->code)->toBe('CC-100')
        ->and($dto->name)->toBe('R&D Department')
        ->and($dto->dimensionType)->toBe(DimensionType::DEPARTMENT)
        ->and($dto->toArray()['dimension_type'])->toBe('department');

    expect(fn() => CreateCostCenterDto::from(['name' => 'Only Name']))
        ->toThrow(InvalidArgumentException::class, 'Cost center code is required.');
    expect(fn() => CreateCostCenterDto::from(['code' => 'CC-1']))
        ->toThrow(InvalidArgumentException::class, 'Cost center name is required.');
});

test('CreateCurrencyDto and UpdateCurrencyDto enforce ISO 4217 rules and precision bounds', function () {
    $dto = CreateCurrencyDto::from([
        'code' => 'gbp',
        'name' => ' British Pound ',
        'symbol' => '£',
        'precision' => 2,
        'exchange_rate' => 1.27,
    ]);

    expect($dto->code)->toBe('GBP')
        ->and($dto->name)->toBe('British Pound')
        ->and($dto->toArray()['exchange_rate'])->toBe(1.27);

    expect(fn() => new CreateCurrencyDto(code: 'US', name: 'Dollar', symbol: '$'))
        ->toThrow(InvalidArgumentException::class, 'Currency code must be a 3-character ISO 4217 code');
    expect(fn() => new CreateCurrencyDto(code: 'USD', name: '   ', symbol: '$'))
        ->toThrow(InvalidArgumentException::class, 'Currency name cannot be empty.');
    expect(fn() => new CreateCurrencyDto(code: 'USD', name: 'Dollar', symbol: '   '))
        ->toThrow(InvalidArgumentException::class, 'Currency symbol cannot be empty.');
    expect(fn() => new CreateCurrencyDto(code: 'USD', name: 'Dollar', symbol: '$', precision: 7))
        ->toThrow(InvalidArgumentException::class, 'Currency precision must be between 0 and 6 decimal places.');

    $updateDto = UpdateCurrencyDto::from([
        'name' => ' Sterling ',
        'precision' => 2,
        'is_active' => true,
    ]);
    expect($updateDto->toArray())->toBe([
        'name' => 'Sterling',
        'precision' => 2,
        'is_active' => true,
    ]);

    expect(fn() => new UpdateCurrencyDto(precision: -1))
        ->toThrow(InvalidArgumentException::class, 'Currency precision must be between 0 and 6 decimal places.');
});

test('SetExchangeRateDto and CurrencyConversionResultDto validate and serialize accurately', function () {
    $rateDto = SetExchangeRateDto::from([
        'currency_code' => 'eur',
        'rate' => 1.085,
        'effective_date' => '2026-05-01',
    ]);

    expect($rateDto->currencyCode)->toBe('EUR')
        ->and($rateDto->toArray()['rate'])->toBe(1.085);

    expect(fn() => new SetExchangeRateDto(currencyCode: 'INVALID', rate: 1.0))
        ->toThrow(InvalidArgumentException::class, '3-character ISO 4217 code');
    expect(fn() => new SetExchangeRateDto(currencyCode: 'EUR', rate: 0.0))
        ->toThrow(InvalidArgumentException::class, 'Exchange rate must be strictly positive');

    $convDto = CurrencyConversionResultDto::from([
        'amount' => 100,
        'from_currency' => 'eur',
        'to_currency' => 'usd',
        'converted_amount' => 108.5,
        'rate' => 1.085,
        'effective_date' => '2026-05-01',
    ]);

    expect($convDto->fromCurrency)->toBe('EUR')
        ->and($convDto->precision)->toBe(2)
        ->and($convDto->toArray()['converted_amount'])->toBe(108.5);
});

test('CreateFiscalYearDto, CreateFiscalPeriodDto, and FiscalYearCloseDto validate and serialize', function () {
    $yearDto = CreateFiscalYearDto::from([
        'name' => ' FY 2026 ',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);

    expect($yearDto->name)->toBe('FY 2026')
        ->and($yearDto->status)->toBe(FiscalPeriodStatus::OPEN)
        ->and($yearDto->toArray()['status'])->toBe('open');

    $periodDto = CreateFiscalPeriodDto::from([
        'fiscal_year_id' => 1,
        'period_number' => 1,
        'name' => ' Jan 2026 ',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'status' => FiscalPeriodStatus::OPEN,
    ]);

    expect($periodDto->name)->toBe('Jan 2026')
        ->and($periodDto->toArray()['period_number'])->toBe(1);

    $closeDto = FiscalYearCloseDto::from([
        'fiscal_year_id' => 1,
        'actor_id' => ' cfo-user ',
        'retained_earnings_account_code' => '3200',
    ]);

    expect($closeDto->actorId)->toBe('cfo-user')
        ->and($closeDto->retainedEarningsAccountCode)->toBe('3200');

    expect(fn() => new FiscalYearCloseDto(fiscalYearId: 1, actorId: '   '))
        ->toThrow(InvalidArgumentException::class, 'actor_id is required.');
});

test('JournalEntryLineDto and CreateJournalEntryDto support minor and major unit factories', function () {
    expect(fn() => new CreateJournalEntryDto(entryDate: '2026-01-01', lines: []))
        ->toThrow(InvalidArgumentException::class, 'Journal entry must contain at least one line item.');

    expect(fn() => new JournalEntryLineDto(accountId: 1, type: PostingSide::DEBIT, amount: 0))
        ->toThrow(InvalidArgumentException::class, 'Journal line amount must be greater than zero.');

    expect(fn() => new JournalEntryLineDto(accountId: 1, type: PostingSide::DEBIT, amount: 100, amountFunctional: -50))
        ->toThrow(InvalidArgumentException::class, 'Journal line functional amount must be greater than zero.');

    $lineFromMajor = JournalEntryLineDto::fromMajor(
        accountId: 10,
        type: PostingSide::DEBIT,
        amountMajor: 25.50,
        amountFunctionalMajor: 27.54,
        narration: 'Major test'
    );

    expect($lineFromMajor->amount)->toBe(2550)
        ->and($lineFromMajor->amountFunctional)->toBe(2754)
        ->and($lineFromMajor->toMajor())->toBe(25.5)
        ->and($lineFromMajor->toFunctionalMajor())->toBe(27.54);

    $entryDto = CreateJournalEntryDto::fromMajor([
        'entry_date' => '2026-03-15',
        'entry_type' => 'adjusting',
        'currency' => 'eur',
        'lines' => [
            ['account_id' => 1, 'type' => 'debit', 'amount' => 12.50, 'amount_functional' => 13.75],
            $lineFromMajor,
        ],
    ]);

    expect($entryDto->entryType)->toBe(JournalEntryType::ADJUSTING)
        ->and($entryDto->currency)->toBe('EUR')
        ->and($entryDto->lines[0]->amount)->toBe(1250)
        ->and($entryDto->lines[0]->amountFunctional)->toBe(1375)
        ->and($entryDto->toArray()['lines'])->toHaveCount(2);
});

test('Journal workflow DTOs (Submit, Approve, Reject, Post, Reverse) validate inputs', function () {
    $submit = SubmitJournalEntryDto::from(['id' => 5, 'actor_id' => 'maker', 'notes' => 'Ready']);
    expect($submit->entryId)->toBe(5)->and($submit->actorId)->toBe('maker');

    $approve = ApproveJournalEntryDto::from(['entry_id' => 5, 'approver_id' => ' checker ']);
    expect($approve->entryId)->toBe(5)->and($approve->approverId)->toBe('checker');
    expect(fn() => new ApproveJournalEntryDto(entryId: 5, approverId: '  '))
        ->toThrow(InvalidArgumentException::class, 'approver_id is required.');

    $reject = RejectJournalEntryDto::from(['entry_id' => 5, 'reason' => ' Missing receipt ']);
    expect($reject->actorId)->toBe('system')->and($reject->reason)->toBe('Missing receipt');
    expect(fn() => new RejectJournalEntryDto(entryId: 5, actorId: 'checker', reason: '   '))
        ->toThrow(InvalidArgumentException::class, 'Rejection reason is mandatory.');

    $post = PostJournalEntryDto::from(['entry_id' => 5, 'actor_id' => ' accountant ']);
    expect($post->actorId)->toBe('accountant');
    expect(fn() => new PostJournalEntryDto(entryId: 5, actorId: '  '))
        ->toThrow(InvalidArgumentException::class, 'actor_id is required for posting.');

    $reverse = ReverseJournalEntryDto::from([
        'entry_id' => 5,
        'actor_id' => 'auditor',
        'reason' => ' Duplicate invoice ',
        'reversal_date' => '2026-04-01',
    ]);
    expect($reverse->reason)->toBe('Duplicate invoice')
        ->and($reverse->reversalDate)->toBe('2026-04-01');
    expect(fn() => new ReverseJournalEntryDto(entryId: 5, actorId: 'auditor', reason: '  '))
        ->toThrow(InvalidArgumentException::class, 'Reversal reason is mandatory.');
});

test('AclIngestEventDto supports minor and major units and validates required fields', function () {
    $dto = AclIngestEventDto::fromMajor(
        eventName: 'sales.invoice.created',
        documentReference: 'INV-1001',
        amountMajor: 199.95,
        currency: 'eur',
        debitAccountCode: '1200',
        creditAccountCode: '4100',
    );

    expect($dto->amount)->toBe(19995)
        ->and($dto->currency)->toBe('EUR')
        ->and($dto->toMajor())->toBe(199.95)
        ->and($dto->toArray()['document_reference'])->toBe('INV-1001');

    $fromArr = AclIngestEventDto::from([
        'event_name' => 'sales.invoice.created',
        'document_reference' => 'INV-1002',
        'amount_major' => 50.25,
    ]);
    expect($fromArr->amount)->toBe(5025);

    expect(fn() => new AclIngestEventDto(
        eventName: 'test',
        documentReference: 'REF-1',
        transactionDate: '2026-01-01',
        amount: 0
    ))->toThrow(InvalidArgumentException::class, 'Event amount must be positive.');

    expect(fn() => AclIngestEventDto::from(['event_name' => 'test', 'document_reference' => 'REF-1']))
        ->toThrow(InvalidArgumentException::class, 'amount is required.');
});
