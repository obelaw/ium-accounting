<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Queries;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Reports\PartnerLedgerEntry;
use Obelaw\Ium\Accounting\Reports\PartnerLedgerReport;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;

/**
 * Customer / Vendor / Employee Subledger Statement (Partner Ledger)
 * with Opening Balance, chronological Running Balance, and Open Residual Tracking.
 */
final class PartnerLedgerQuery
{
    public function __construct(private readonly ExchangeRateResolver $fxResolver) {}

    public function get(
        string $partyType,
        int $partyId,
        ?int $accountId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): PartnerLedgerReport {
        $from = $fromDate !== null ? Carbon::parse($fromDate)->format('Y-m-d') : null;
        $to = $toDate !== null ? Carbon::parse($toDate)->format('Y-m-d') : null;

        $openingBalance = 0;
        if ($from !== null) {
            $openQuery = GeneralLedgerPosting::query()
                ->where('party_type', $partyType)
                ->where('party_id', $partyId)
                ->whereDate('posting_date', '<', $from);

            if ($accountId !== null) {
                $openQuery->where('account_id', $accountId);
            }

            $openingBalance = (int) (clone $openQuery)->sum('debit') - (int) $openQuery->sum('credit');
        }

        $query = GeneralLedgerPosting::with(['account', 'journalEntry', 'journalEntryLine'])
            ->where('party_type', $partyType)
            ->where('party_id', $partyId)
            ->orderBy('posting_date')
            ->orderBy('id');

        if ($accountId !== null) {
            $query->where('account_id', $accountId);
        }
        if ($from !== null) {
            $query->whereDate('posting_date', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('posting_date', '<=', $to);
        }

        $postings = $query->get();

        $entries = [];
        $running = $openingBalance;
        $totalDebit = 0;
        $totalCredit = 0;
        $totalOpenResidual = 0;

        foreach ($postings as $posting) {
            $dr = (int) $posting->debit;
            $cr = (int) $posting->credit;
            $totalDebit += $dr;
            $totalCredit += $cr;
            $running += ($dr - $cr);

            $line = $posting->journalEntryLine;
            $residual = $line ? $line->residualFunctional() : 0;
            $signedResidual = $dr > 0 ? $residual : -$residual;
            $totalOpenResidual += $signedResidual;

            $entries[] = new PartnerLedgerEntry(
                lineId: $posting->journal_entry_line_id,
                journalEntryId: $posting->journal_entry_id,
                entryNumber: $posting->journalEntry?->entry_number ?? '',
                postingDate: $posting->posting_date->format('Y-m-d'),
                dueDate: $line?->due_date?->format('Y-m-d'),
                accountCode: $posting->account?->code ?? '',
                narration: $posting->narration,
                debit: $dr,
                credit: $cr,
                runningBalance: $running,
                openResidual: $residual,
                reconciliationStatus: $line?->reconciliation_status?->value ?? 'none',
            );
        }

        return new PartnerLedgerReport(
            partyType: $partyType,
            partyId: $partyId,
            currency: $this->fxResolver->baseCurrencyCode(),
            fromDate: $from,
            toDate: $to,
            openingBalance: $openingBalance,
            totalDebit: $totalDebit,
            totalCredit: $totalCredit,
            closingBalance: $running,
            totalOpenResidual: $totalOpenResidual,
            entries: $entries,
        );
    }
}
