<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Support;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\NonPostingAccountException;
use Obelaw\Ium\Accounting\Exceptions\UnbalancedJournalEntryException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Support\Money;

final class JournalLineBuilder
{
    /**
     * Validate all target accounts exist and accept leaf postings, and compute debit/credit sums.
     *
     * @param list<JournalLineData> $lines
     *
     * @return array{total_debit: int, total_credit: int}
     */
    public function validateAndSum(array $lines): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Journal entry requires at least one line item.');
        }

        $accountIds = array_values(array_unique(array_map(static fn (JournalLineData $l): int => $l->accountId, $lines)));
        $accounts = Account::whereIn('id', $accountIds)->get()->keyBy('id');

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            /** @var Account|null $account */
            $account = $accounts->get($line->accountId);
            if ($account === null) {
                throw AccountNotFoundException::forId($line->accountId);
            }

            if (! $account->allowsPostings()) {
                throw NonPostingAccountException::forAccount(
                    $account->code,
                    $account->name,
                    $account->classification->value,
                );
            }

            if ($line->type === PostingSide::DEBIT) {
                $totalDebit += $line->amount;
            } else {
                $totalCredit += $line->amount;
            }
        }

        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
        ];
    }

    /**
     * Allocate functional minor amounts across debit and credit sides independently
     * via Largest-Remainder (`Money::allocate()`) to guarantee ZERO FX rounding drift.
     *
     * @param list<JournalLineData> $lines
     *
     * @return array<int, int>
     */
    public function allocateFunctional(
        array $lines,
        float $exchangeRate,
        string $transactionCurrency,
        string $baseCurrency,
    ): array {
        $result = [];
        $debitIndices = [];
        $debitWeights = [];
        $creditIndices = [];
        $creditWeights = [];

        $fromDecimals = Money::decimalsFor($transactionCurrency);
        $toDecimals = Money::decimalsFor($baseCurrency);
        $scaleFactor = (10 ** $toDecimals) / (10 ** $fromDecimals);

        foreach ($lines as $idx => $line) {
            if ($line->amountFunctional !== null) {
                $result[$idx] = $line->amountFunctional;

                continue;
            }

            if ($exchangeRate === 1.0 && $fromDecimals === $toDecimals) {
                $result[$idx] = $line->amount;

                continue;
            }

            if ($line->type === PostingSide::DEBIT) {
                $debitIndices[] = $idx;
                $debitWeights[] = max(1, $line->amount);
            } else {
                $creditIndices[] = $idx;
                $creditWeights[] = max(1, $line->amount);
            }
        }

        if ($debitIndices !== []) {
            $debitSum = array_sum(array_map(static fn (int $i): int => $lines[$i]->amount, $debitIndices));
            $targetFunctional = (int) round($debitSum * $exchangeRate * $scaleFactor, 0, PHP_ROUND_HALF_UP);
            $allocated = Money::minor($targetFunctional, $baseCurrency)->allocate($debitWeights);

            foreach ($debitIndices as $pos => $idx) {
                $result[$idx] = $allocated[$pos]->amount;
            }
        }

        if ($creditIndices !== []) {
            $creditSum = array_sum(array_map(static fn (int $i): int => $lines[$i]->amount, $creditIndices));
            $targetFunctional = (int) round($creditSum * $exchangeRate * $scaleFactor, 0, PHP_ROUND_HALF_UP);
            $allocated = Money::minor($targetFunctional, $baseCurrency)->allocate($creditWeights);

            foreach ($creditIndices as $pos => $idx) {
                $result[$idx] = $allocated[$pos]->amount;
            }
        }

        return $result;
    }

    /**
     * Assert strict Zero-Sum invariant in BOTH transaction currency and functional base currency.
     */
    public function assertZeroSum(JournalEntry $entry): void
    {
        $lines = $entry->relationLoaded('lines') ? $entry->lines : $entry->lines()->get();

        if ($lines->isEmpty()) {
            throw new InvalidArgumentException("Voucher [{$entry->entry_number}] has no line items.");
        }

        $txDebit = 0;
        $txCredit = 0;
        $fnDebit = 0;
        $fnCredit = 0;

        foreach ($lines as $line) {
            if ($line->type === PostingSide::DEBIT) {
                $txDebit += (int) $line->amount;
                $fnDebit += (int) $line->amount_functional;
            } else {
                $txCredit += (int) $line->amount;
                $fnCredit += (int) $line->amount_functional;
            }
        }

        if ($txDebit !== $txCredit) {
            throw UnbalancedJournalEntryException::forVoucher(
                $entry->entry_number,
                $txDebit,
                $txCredit,
                abs($txDebit - $txCredit),
            );
        }

        if ($fnDebit !== $fnCredit) {
            throw UnbalancedJournalEntryException::forVoucher(
                $entry->entry_number,
                $fnDebit,
                $fnCredit,
                abs($fnDebit - $fnCredit),
            );
        }
    }
}
