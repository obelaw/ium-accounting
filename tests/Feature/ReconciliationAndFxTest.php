<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountData;
use Obelaw\Ium\Accounting\Data\CreateCurrencyData;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\FxRevaluationData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Data\ReconcileLinesData;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Enums\ReconciliationStatus;
use Obelaw\Ium\Accounting\Exceptions\ReconciliationException;

it('supports partial and full reconciliation, posts automatic Realized FX Gain/Loss, and supports unreconcile()', function () {
    ium()->accounting()->currencies()->base(); // USD
    ium()->accounting()->currencies()->create(new CreateCurrencyData('EUR', 'Euro', '€', exchangeRate: 1.10));

    $ar = ium()->accounting()->accounts()->create(new CreateAccountData('1200', 'Accounts Receivable', AccountType::ASSET, isReconcilable: true));
    $bank = ium()->accounting()->accounts()->create(new CreateAccountData('1020', 'Bank', AccountType::ASSET));
    $rev = ium()->accounting()->accounts()->create(new CreateAccountData('4010', 'Sales Revenue', AccountType::REVENUE));
    $fxGain = ium()->accounting()->accounts()->create(new CreateAccountData('4910', 'Realized FX Gain', AccountType::REVENUE));
    $fxLoss = ium()->accounting()->accounts()->create(new CreateAccountData('5910', 'Realized FX Loss', AccountType::EXPENSE));

    // 1. Invoice €1,000.00 (100000 EUR cents) @ 1.10 = $1,100.00 (110000 USD cents)
    $inv = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
        entryDate: '2026-05-01',
        currency: 'EUR',
        exchangeRate: 1.10,
        lines: [
            new JournalLineData($ar->id, PostingSide::DEBIT, 100000, partyType: 'customer', partyId: 7, dueDate: '2026-05-31'),
            new JournalLineData($rev->id, PostingSide::CREDIT, 100000),
        ],
    ));
    $inv = ium()->accounting()->journalEntries()->post($inv->id, 'sys', bypassApproval: true);
    $arDebitLine = $inv->lines->firstWhere('account_id', $ar->id);

    expect($arDebitLine->reconciliation_status)->toBe(ReconciliationStatus::OPEN)
        ->and($ar->fresh()->current_balance)->toBe(110000);

    // 2. Payment of €1,000.00 collected @ 1.15 = $1,150.00 (115000 USD cents) -> Realized FX Gain of $50.00 (5000 cents)!
    $pmt = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
        entryDate: '2026-05-20',
        currency: 'EUR',
        exchangeRate: 1.15,
        lines: [
            new JournalLineData($bank->id, PostingSide::DEBIT, 100000),
            new JournalLineData($ar->id, PostingSide::CREDIT, 100000, partyType: 'customer', partyId: 7),
        ],
    ));
    $pmt = ium()->accounting()->journalEntries()->post($pmt->id, 'sys', bypassApproval: true);
    $arCreditLine = $pmt->lines->firstWhere('account_id', $ar->id);

    // Partial reconcile €400.00 first (40000 cents) -> FX gain = 400 * (1.15 - 1.10) = $20.00 (2000 cents)
    $rec1 = ium()->accounting()->reconciliations()->reconcile(new ReconcileLinesData(
        debitLineIds: [$arDebitLine->id],
        creditLineIds: [$arCreditLine->id],
        reconciledDate: '2026-05-20',
        amount: 40000,
    ));

    expect($rec1->total_amount)->toBe(40000)
        ->and($rec1->fx_gain_loss_entry_id)->not->toBeNull()
        ->and($arDebitLine->fresh()->reconciliation_status)->toBe(ReconciliationStatus::PARTIAL)
        ->and($arDebitLine->fresh()->residualAmount())->toBe(60000)
        ->and($fxGain->fresh()->current_balance)->toBe(2000);

    // Cannot reverse an entry while lines are reconciled
    expect(fn () => ium()->accounting()->journalEntries()->reverse($inv->id, 'Try reverse'))
        ->toThrow(ReconciliationException::class);

    // Reconcile remaining €600.00 -> FX gain += $30.00 (3000 cents), AR functional balance MUST be 0!
    $rec2 = ium()->accounting()->reconciliations()->reconcile(new ReconcileLinesData(
        debitLineIds: [$arDebitLine->id],
        creditLineIds: [$arCreditLine->id],
        reconciledDate: '2026-05-20',
    ));

    expect($rec2->total_amount)->toBe(60000)
        ->and($arDebitLine->fresh()->reconciliation_status)->toBe(ReconciliationStatus::RECONCILED)
        ->and($arCreditLine->fresh()->reconciliation_status)->toBe(ReconciliationStatus::RECONCILED)
        ->and($fxGain->fresh()->current_balance)->toBe(5000)
        ->and($ar->fresh()->current_balance)->toBe(0);

    // Unreconcile $rec2 -> reverses FX gain entry and restores 60000 open residual!
    ium()->accounting()->reconciliations()->unreconcile($rec2->id, 'Audit correction');

    expect($arDebitLine->fresh()->reconciliation_status)->toBe(ReconciliationStatus::PARTIAL)
        ->and($arDebitLine->fresh()->residualAmount())->toBe(60000)
        ->and($fxGain->fresh()->current_balance)->toBe(2000);
});

it('runs period-end Unrealized FX Revaluation with optional next-period auto-reversal', function () {
    ium()->accounting()->currencies()->base(); // USD
    ium()->accounting()->currencies()->create(new CreateCurrencyData('EUR', 'Euro', '€', exchangeRate: 1.10));

    $ar = ium()->accounting()->accounts()->create(new CreateAccountData('1200', 'AR EUR', AccountType::ASSET, isReconcilable: true));
    $rev = ium()->accounting()->accounts()->create(new CreateAccountData('4010', 'Sales', AccountType::REVENUE));
    $unrGain = ium()->accounting()->accounts()->create(new CreateAccountData('4920', 'Unrealized FX Gain', AccountType::REVENUE));
    ium()->accounting()->accounts()->create(new CreateAccountData('5920', 'Unrealized FX Loss', AccountType::EXPENSE));

    // Open €1,000.00 receivable booked @ 1.10 = $1,100.00
    $inv = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
        entryDate: '2026-05-10',
        currency: 'EUR',
        exchangeRate: 1.10,
        lines: [
            new JournalLineData($ar->id, PostingSide::DEBIT, 100000),
            new JournalLineData($rev->id, PostingSide::CREDIT, 100000),
        ],
    ));
    ium()->accounting()->journalEntries()->post($inv->id, 'sys', bypassApproval: true);

    // Month-end closing rate rises to 1.20 -> Revalued $1,200.00 -> Unrealized Gain = +$100.00 (10000 cents)
    $result = ium()->accounting()->fx()->revalue(new FxRevaluationData(
        currency: 'EUR',
        closingRate: 1.20,
        asOfDate: '2026-05-31',
        autoReverseDate: '2026-06-01',
    ));

    expect($result->netGainLoss)->toBe(10000)
        ->and($result->revaluationEntry)->not->toBeNull()
        ->and($result->autoReversalEntry)->not->toBeNull();

    // As of 2026-05-31, Balance Sheet shows AR = $1,200.00 & Unrealized Gain = $100.00
    $bsMay = ium()->accounting()->reports()->balanceSheet('2026-05-31');
    expect($bsMay->isBalanced)->toBeTrue()
        ->and($bsMay->totalAssets)->toBe(120000);

    // As of 2026-06-01 (after auto-reversal), AR returns to original $1,100.00 and Unrealized Gain nets to 0
    $bsJun = ium()->accounting()->reports()->balanceSheet('2026-06-01');
    expect($bsJun->isBalanced)->toBeTrue()
        ->and($bsJun->totalAssets)->toBe(110000)
        ->and($unrGain->fresh()->current_balance)->toBe(0);
});
