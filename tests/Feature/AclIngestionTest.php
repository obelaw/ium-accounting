<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\AclIngestEventDto;
use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
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
        ->and($entry->total_debit)->toBe(4500.0)
        ->and($entry->total_credit)->toBe(4500.0)
        ->and($this->ar->fresh()->current_balance)->toBe(4500.0)
        ->and($this->sales->fresh()->current_balance)->toBe(4500.0);
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

    expect($this->ar->fresh()->current_balance)->toBe(4500.0);

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
        ->and($this->bank->fresh()->current_balance)->toBe(4500.0)
        ->and($this->ar->fresh()->current_balance)->toBe(0.0);
});
