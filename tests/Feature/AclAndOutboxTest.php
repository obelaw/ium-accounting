<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\AclIngestData;
use Obelaw\Ium\Accounting\Data\CreateAccountData;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Facades\Ium;

it('ingests posting rules, compound multi-line idempotent ACL events, and transactional Outbox messages', function () {
    ium()->accounting()->journals()->seedDefaults();

    $ar = ium()->accounting()->accounts()->create(new CreateAccountData('1200', 'AR', AccountType::ASSET, isReconcilable: true));
    $sales = ium()->accounting()->accounts()->create(new CreateAccountData('4010', 'Product Sales', AccountType::REVENUE));
    $vat = ium()->accounting()->accounts()->create(new CreateAccountData('2150', 'Output VAT', AccountType::LIABILITY));
    $cash = ium()->accounting()->accounts()->create(new CreateAccountData('1010', 'Cash', AccountType::ASSET));

    // 1. Posting Rule ingestion
    $sj = ium()->accounting()->journals()->findByCode('SJ');
    ium()->accounting()->acl()->registerRule('sales.cash_receipt', '1010', '1200', journalId: $sj->id);

    $receipt = ium()->accounting()->acl()->ingest(new AclIngestData(
        eventName: 'sales.cash_receipt',
        documentReference: 'RCP-001',
        transactionDate: '2026-05-05',
        amount: 5000,
        idempotencyKey: 'sales:rcp:001',
        partyType: 'customer',
        partyId: 9,
    ));

    expect($receipt->isPosted())->toBeTrue()
        ->and($receipt->entry_number)->toStartWith('SJ-2026-')
        ->and($cash->fresh()->current_balance)->toBe(5000);

    // Idempotent replay returns same entry without duplicate posting
    $replay = ium()->accounting()->acl()->ingest(new AclIngestData(
        eventName: 'sales.cash_receipt',
        documentReference: 'RCP-001',
        transactionDate: '2026-05-05',
        amount: 5000,
        idempotencyKey: 'sales:rcp:001',
    ));

    expect($replay->id)->toBe($receipt->id)
        ->and(JournalEntry::count())->toBe(1);

    // 2. Compound multi-line Outbox ingestion
    $msg = Ium::outbox()->publish(
        topic: 'sales.invoice_issued',
        payload: [
            'document_reference' => 'INV-900',
            'transaction_date' => '2026-05-06',
            'journal_code' => 'SJ',
            'party_type' => 'customer',
            'party_id' => 9,
            'lines' => [
                ['account_code' => '1200', 'type' => 'debit', 'amount' => 11500, 'due_date' => '2026-06-05'],
                ['account_code' => '4010', 'type' => 'credit', 'amount' => 10000],
                ['account_code' => '2150', 'type' => 'credit', 'amount' => 1500],
            ],
        ],
        idempotencyKey: 'sales:inv:900',
    );

    $invEntry = ium()->accounting()->acl()->ingestFromOutbox($msg);
    $invReplay = ium()->accounting()->acl()->ingestFromOutbox($msg);

    expect($invEntry->id)->toBe($invReplay->id)
        ->and($invEntry->isPosted())->toBeTrue()
        ->and($invEntry->lines)->toHaveCount(3)
        ->and($sales->fresh()->current_balance)->toBe(10000)
        ->and($vat->fresh()->current_balance)->toBe(1500)
        ->and($ar->fresh()->current_balance)->toBe(6500);
});
