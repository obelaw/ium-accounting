<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\AclIngestEventDto;
use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\AclIngestionService;

beforeEach(function () {
    $this->accountService = app(AccountService::class);
    $this->aclService = app(AclIngestionService::class);

    $this->ar = $this->accountService->create(CreateAccountDto::from([
        'code' => '1200',
        'name' => 'Trade Receivables',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->sales = $this->accountService->create(CreateAccountDto::from([
        'code' => '4000',
        'name' => 'Commercial Sales Revenue',
        'type' => AccountType::REVENUE,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->bank = $this->accountService->create(CreateAccountDto::from([
        'code' => '1020',
        'name' => 'Bank Clearing',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));
});

test('anti-corruption layer ingests upstream InvoiceCreated event and generates posted journal voucher', function () {
    // Register mapping: InvoiceCreated -> Debit AR (1200), Credit Sales (4000)
    $this->aclService->registerMapping('InvoiceCreated', '1200', '4000');

    $entry = $this->aclService->ingest(AclIngestEventDto::from([
        'event_name' => 'InvoiceCreated',
        'document_reference' => 'INV-2026-0042',
        'transaction_date' => '2026-08-10',
        'amount' => 4500.0,
        'actor_id' => 'sales-module',
        'auto_post' => true,
    ]));

    expect($entry->isPosted())->toBeTrue()
        ->and($entry->total_debit)->toBe(4500)
        ->and($entry->total_credit)->toBe(4500)
        ->and($this->ar->fresh()->current_balance)->toBe(4500)
        ->and($this->sales->fresh()->current_balance)->toBe(4500);
});

test('anti-corruption layer ingests PaymentReceived event and clears receivables', function () {
    // 1. Initial Invoice creates 4500.0 receivable
    $this->aclService->registerMapping('InvoiceCreated', '1200', '4000');
    $this->aclService->ingest(AclIngestEventDto::from([
        'event_name' => 'InvoiceCreated',
        'document_reference' => 'INV-2026-0099',
        'transaction_date' => '2026-08-10',
        'amount' => 4500.0,
        'actor_id' => 'sales-module',
        'auto_post' => true,
    ]));

    expect($this->ar->fresh()->current_balance)->toBe(4500);

    // 2. Payment received clears receivable: Debit Bank (1020), Credit AR (1200)
    $this->aclService->registerMapping('PaymentReceived', '1020', '1200');

    $entry = $this->aclService->ingest(AclIngestEventDto::from([
        'event_name' => 'PaymentReceived',
        'document_reference' => 'PAY-2026-0099',
        'transaction_date' => '2026-08-12',
        'amount' => 4500.0,
        'actor_id' => 'billing-module',
        'auto_post' => true,
    ]));

    expect($entry->isPosted())->toBeTrue()
        ->and($this->bank->fresh()->current_balance)->toBe(4500)
        ->and($this->ar->fresh()->current_balance)->toBe(0);
});

test('anti-corruption layer supports explicit account codes and draft mode without auto_post', function () {
    $dto = AclIngestEventDto::fromMajor(
        eventName: 'DirectSaleSettled',
        documentReference: 'POS-2026-777',
        amountMajor: 199.95,
        transactionDate: '2026-08-20',
        debitAccountCode: '1020',
        creditAccountCode: '4000',
        narration: 'POS settlement batch #777',
        autoPost: false,
        metadata: ['terminal' => 'POS-01']
    );

    expect($dto->amount)->toBe(19995)
        ->and($dto->toMajor())->toBe(199.95);

    $entry = $this->aclService->ingest($dto);

    expect($entry->isDraft())->toBeTrue()
        ->and($entry->isPosted())->toBeFalse()
        ->and($entry->total_debit)->toBe(19995)
        ->and($entry->narration)->toBe('POS settlement batch #777')
        ->and($entry->metadata['acl_event_name'])->toBe('DirectSaleSettled')
        ->and($entry->metadata['terminal'])->toBe('POS-01')
        ->and($this->bank->fresh()->current_balance)->toBe(0);
});

test('anti-corruption layer rejects unmapped events and non-existent target accounts', function () {
    expect(fn() => $this->aclService->ingest(AclIngestEventDto::from([
        'event_name' => 'UnregisteredDomainEvent',
        'document_reference' => 'DOC-001',
        'amount' => 1000,
    ])))->toThrow(InvalidArgumentException::class, 'No account mapping found for event "UnregisteredDomainEvent"');

    expect(fn() => $this->aclService->ingest(AclIngestEventDto::from([
        'event_name' => 'CustomEvent',
        'document_reference' => 'DOC-002',
        'amount' => 1000,
        'debit_account_code' => '9999',
        'credit_account_code' => '4000',
    ])))->toThrow(AccountNotFoundException::class);

    expect(fn() => $this->aclService->ingest(AclIngestEventDto::from([
        'event_name' => 'CustomEvent',
        'document_reference' => 'DOC-003',
        'amount' => 1000,
        'debit_account_code' => '1200',
        'credit_account_code' => '8888',
    ])))->toThrow(AccountNotFoundException::class);
});
