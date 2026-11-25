<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Queries;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Reports\TrialBalanceReport;
use Obelaw\Ium\Accounting\Reports\TrialBalanceRow;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;

/**
 * Generates an enterprise Trial Balance with Opening Balance, Period Movement,
 * and Ending Balance via at most 2 aggregated GROUP BY queries (zero N+1).
 */
final class TrialBalanceQuery
{
    public function __construct(private readonly ExchangeRateResolver $fxResolver) {}

    public function get(
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $fiscalPeriodId = null,
        bool $includeZeroRows = false,
    ): TrialBalanceReport {
        if ($fiscalPeriodId !== null) {
            $period = FiscalPeriod::findOrFail($fiscalPeriodId);
            $fromDate ??= $period->start_date->format('Y-m-d');
            $toDate ??= $period->end_date->format('Y-m-d');
        } else {
            $fromDate = $fromDate !== null ? Carbon::parse($fromDate)->format('Y-m-d') : null;
            $toDate = $toDate !== null ? Carbon::parse($toDate)->format('Y-m-d') : null;
        }

        $accounts = Account::postingAccounts()->orderBy('code')->get();

        // 1. Opening balances strictly prior to $fromDate
        $openingAggs = collect();
        if ($fromDate !== null) {
            $openingAggs = GeneralLedgerPosting::query()
                ->selectRaw('account_id, COALESCE(SUM(debit), 0) as sum_debit, COALESCE(SUM(credit), 0) as sum_credit')
                ->whereDate('posting_date', '<', $fromDate)
                ->groupBy('account_id')
                ->get()
                ->keyBy('account_id');
        }

        // 2. Period movements
        $periodQuery = GeneralLedgerPosting::query()
            ->selectRaw('account_id, COALESCE(SUM(debit), 0) as sum_debit, COALESCE(SUM(credit), 0) as sum_credit')
            ->groupBy('account_id');

        if ($fiscalPeriodId !== null) {
            $periodQuery->where('fiscal_period_id', $fiscalPeriodId);
        } else {
            if ($fromDate !== null) {
                $periodQuery->whereDate('posting_date', '>=', $fromDate);
            }
            if ($toDate !== null) {
                $periodQuery->whereDate('posting_date', '<=', $toDate);
            }
        }

        $periodAggs = $periodQuery->get()->keyBy('account_id');

        $rows = [];
        $totOpenDr = 0;
        $totOpenCr = 0;
        $totPerDr = 0;
        $totPerCr = 0;
        $totEndDr = 0;
        $totEndCr = 0;

        foreach ($accounts as $account) {
            $openAgg = $openingAggs->get($account->id);
            $openSigned = ($openAgg ? (int) $openAgg->sum_debit : 0) - ($openAgg ? (int) $openAgg->sum_credit : 0);
            $openDr = $openSigned > 0 ? $openSigned : 0;
            $openCr = $openSigned < 0 ? abs($openSigned) : 0;

            $perAgg = $periodAggs->get($account->id);
            $perDr = $perAgg ? (int) $perAgg->sum_debit : 0;
            $perCr = $perAgg ? (int) $perAgg->sum_credit : 0;

            $endSigned = $openSigned + ($perDr - $perCr);
            $endDr = $endSigned > 0 ? $endSigned : 0;
            $endCr = $endSigned < 0 ? abs($endSigned) : 0;

            if (! $includeZeroRows && $openDr === 0 && $openCr === 0 && $perDr === 0 && $perCr === 0) {
                continue;
            }

            $totOpenDr += $openDr;
            $totOpenCr += $openCr;
            $totPerDr += $perDr;
            $totPerCr += $perCr;
            $totEndDr += $endDr;
            $totEndCr += $endCr;

            $rows[] = new TrialBalanceRow(
                accountId: $account->id,
                accountCode: $account->code,
                accountName: $account->name,
                type: $account->type->value,
                normalBalance: $account->normal_balance->value,
                openingDebit: $openDr,
                openingCredit: $openCr,
                periodDebit: $perDr,
                periodCredit: $perCr,
                endingDebit: $endDr,
                endingCredit: $endCr,
            );
        }

        $imbalance = abs($totEndDr - $totEndCr);

        return new TrialBalanceReport(
            currency: $this->fxResolver->baseCurrencyCode(),
            precision: $this->fxResolver->basePrecision(),
            fromDate: $fromDate,
            toDate: $toDate,
            rows: $rows,
            totalOpeningDebit: $totOpenDr,
            totalOpeningCredit: $totOpenCr,
            totalPeriodDebit: $totPerDr,
            totalPeriodCredit: $totPerCr,
            totalEndingDebit: $totEndDr,
            totalEndingCredit: $totEndCr,
            isBalanced: $imbalance === 0,
            imbalance: $imbalance,
        );
    }
}
