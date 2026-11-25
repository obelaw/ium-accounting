<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Queries;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Reports\AccountLedgerEntry;
use Obelaw\Ium\Accounting\Reports\AccountLedgerReport;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;

/**
 * Generates an Account General Ledger statement with exact Opening Balance,
 * chronological running balance, and Closing Balance.
 */
final class AccountLedgerQuery
{
    public function __construct(private readonly ExchangeRateResolver $fxResolver) {}

    public function get(
        int $accountId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $costCenterId = null,
    ): AccountLedgerReport {
        $account = Account::find($accountId) ?? throw AccountNotFoundException::forId($accountId);
        $from = $fromDate !== null ? Carbon::parse($fromDate)->format('Y-m-d') : null;
        $to = $toDate !== null ? Carbon::parse($toDate)->format('Y-m-d') : null;

        $openingBalance = 0;
        if ($from !== null) {
            $openQuery = GeneralLedgerPosting::query()
                ->where('account_id', $account->id)
                ->whereDate('posting_date', '<', $from);

            if ($costCenterId !== null) {
                $openQuery->where('cost_center_id', $costCenterId);
            }

            $openDr = (int) (clone $openQuery)->sum('debit');
            $openCr = (int) $openQuery->sum('credit');

            $openingBalance = $account->normal_balance === NormalBalance::DEBIT
                ? ($openDr - $openCr)
                : ($openCr - $openDr);
        }

        $query = GeneralLedgerPosting::with('journalEntry')
            ->where('account_id', $account->id)
            ->orderBy('posting_date')
            ->orderBy('id');

        if ($from !== null) {
            $query->whereDate('posting_date', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('posting_date', '<=', $to);
        }
        if ($costCenterId !== null) {
            $query->where('cost_center_id', $costCenterId);
        }

        $postings = $query->get();

        $entries = [];
        $running = $openingBalance;
        $periodDebit = 0;
        $periodCredit = 0;

        foreach ($postings as $posting) {
            $dr = (int) $posting->debit;
            $cr = (int) $posting->credit;
            $periodDebit += $dr;
            $periodCredit += $cr;

            $delta = $account->normal_balance === NormalBalance::DEBIT ? ($dr - $cr) : ($cr - $dr);
            $running += $delta;

            $entries[] = new AccountLedgerEntry(
                postingId: $posting->id,
                journalEntryId: $posting->journal_entry_id,
                entryNumber: $posting->journalEntry?->entry_number ?? '',
                postingDate: $posting->posting_date->format('Y-m-d'),
                narration: $posting->narration,
                debit: $dr,
                credit: $cr,
                runningBalance: $running,
                currency: $posting->currency,
                amountCurrency: (int) $posting->amount_currency,
            );
        }

        return new AccountLedgerReport(
            accountId: $account->id,
            accountCode: $account->code,
            accountName: $account->name,
            normalBalance: $account->normal_balance->value,
            currency: $this->fxResolver->baseCurrencyCode(),
            fromDate: $from,
            toDate: $to,
            openingBalance: $openingBalance,
            periodDebit: $periodDebit,
            periodCredit: $periodCredit,
            closingBalance: $running,
            entries: $entries,
        );
    }
}
