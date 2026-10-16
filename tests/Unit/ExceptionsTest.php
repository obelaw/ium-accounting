<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\BaseCurrencyRateImmutableException;
use Obelaw\Ium\Accounting\Exceptions\CurrencyNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\ExchangeRateNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\FiscalPeriodClosedException;
use Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException;
use Obelaw\Ium\Accounting\Exceptions\InvalidStateTransitionException;
use Obelaw\Ium\Accounting\Exceptions\MakerCheckerViolationException;
use Obelaw\Ium\Accounting\Exceptions\NonPostingAccountException;
use Obelaw\Ium\Accounting\Exceptions\ReversalNotAllowedException;
use Obelaw\Ium\Accounting\Exceptions\UnbalancedJournalEntryException;

test('AccountNotFoundException formats messages for code and ID', function () {
    expect(AccountNotFoundException::forCode('1110')->getMessage())
        ->toBe('Account with code "1110" not found in the Chart of Accounts.')
        ->and(AccountNotFoundException::forId(42)->getMessage())
        ->toBe('Account with ID 42 not found in the Chart of Accounts.');
});

test('Currency and ExchangeRate exceptions format messages accurately', function () {
    expect(BaseCurrencyRateImmutableException::cannotAlterBaseRate('usd', 1.25)->getMessage())
        ->toContain('Base functional currency "USD" rate is strictly immutable and must equal 1.000000')
        ->and(CurrencyNotFoundException::forCode('chf')->getMessage())
        ->toBe('Currency with ISO code "CHF" not found in the currency registry.')
        ->and(CurrencyNotFoundException::forId(99)->getMessage())
        ->toBe('Currency with ID 99 not found in the currency registry.')
        ->and(ExchangeRateNotFoundException::forCurrency('eur')->getMessage())
        ->toBe('Exchange rate not found for currency "EUR".')
        ->and(ExchangeRateNotFoundException::forCurrency('eur', '2026-03-01')->getMessage())
        ->toBe('Exchange rate not found for currency "EUR" on or before date "2026-03-01".')
        ->and(ExchangeRateNotFoundException::forPair('eur', 'gbp')->getMessage())
        ->toBe('Unable to resolve exchange cross-rate from "EUR" to "GBP".')
        ->and(ExchangeRateNotFoundException::forPair('eur', 'gbp', '2026-03-01')->getMessage())
        ->toBe('Unable to resolve exchange cross-rate from "EUR" to "GBP" on or before date "2026-03-01".');
});

test('Governance, Immutability, and Zero-Sum exceptions format messages accurately', function () {
    expect(FiscalPeriodClosedException::forPeriod('Jan 2026', 'locked')->getMessage())
        ->toContain('Cannot post transactions into fiscal period "Jan 2026" because its status is "locked".')
        ->and(ImmutableJournalEntryException::cannotMutate('JE-2026-0001', 'update')->getMessage())
        ->toContain('Journal voucher "JE-2026-0001" is posted to the General Ledger and cannot be updated.')
        ->and(ImmutableJournalEntryException::cannotMutate('JE-2026-0001', 'delete')->getMessage())
        ->toContain('cannot be deleted.')
        ->and(InvalidStateTransitionException::transition('JE-001', 'draft', 'posted')->getMessage())
        ->toBe('Invalid state transition for voucher "JE-001": Cannot transition from "draft" to "posted".')
        ->and(MakerCheckerViolationException::sameActor('user-alice')->getMessage())
        ->toContain('The approver ("user-alice") cannot be the same actor who created the journal voucher.')
        ->and(NonPostingAccountException::forAccount('1000', 'Assets', 'class')->getMessage())
        ->toContain('Account "1000 - Assets" is a "class" aggregator')
        ->and(ReversalNotAllowedException::notPosted('JE-001', 'draft')->getMessage())
        ->toContain('Cannot reverse journal voucher "JE-001" because it is in "draft" state.')
        ->and(ReversalNotAllowedException::alreadyReversed('JE-001', 'JE-002')->getMessage())
        ->toBe('Journal voucher "JE-001" has already been reversed by voucher "JE-002".')
        ->and(UnbalancedJournalEntryException::forVoucher('JE-001', 10000, 9500, 500)->getMessage())
        ->toContain('Total debits (10000) do not equal total credits (9500). Imbalance is 500.')
        ->and(UnbalancedJournalEntryException::forVoucher('JE-001', 100.0, 95.0, 5.0)->getMessage())
        ->toContain('Total debits (100.0000) do not equal total credits (95.0000). Imbalance is 5.0000.');
});
