<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Queries;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Reports\FinancialStatementLine;
use Obelaw\Ium\Accounting\Reports\IncomeStatementReport;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;

/**
 * Generates an Income Statement (P&L) for any date range, fiscal period,
 * and optional analytical cost center / department / project dimension.
 */
final class IncomeStatementQuery
{
    public function __construct(private readonly ExchangeRateResolver $fxResolver) {}

    public function get(
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $fiscalPeriodId = null,
        ?int $costCenterId = null,
    ): IncomeStatementReport {
        $fromDate = $fromDate !== null ? Carbon::parse($fromDate)->format('Y-m-d') : null;
        $toDate = $toDate !== null ? Carbon::parse($toDate)->format('Y-m-d') : null;

        $accounts = Account::postingAccounts()
            ->whereIn('type', [AccountType::REVENUE->value, AccountType::EXPENSE->value])
            ->orderBy('code')
            ->get();

        $query = GeneralLedgerPosting::query()
            ->selectRaw('account_id, COALESCE(SUM(debit), 0) as sum_debit, COALESCE(SUM(credit), 0) as sum_credit')
            ->whereIn('account_id', $accounts->pluck('id'))
            ->groupBy('account_id');

        if ($fiscalPeriodId !== null) {
            $query->where('fiscal_period_id', $fiscalPeriodId);
        }
        if ($fromDate !== null) {
            $query->whereDate('posting_date', '>=', $fromDate);
        }
        if ($toDate !== null) {
            $query->whereDate('posting_date', '<=', $toDate);
        }
        if ($costCenterId !== null) {
            $query->where('cost_center_id', $costCenterId);
        }

        $aggregates = $query->get()->keyBy('account_id');

        $revenues = [];
        $expenses = [];
        $totalRevenue = 0;
        $totalExpense = 0;

        foreach ($accounts as $account) {
            $agg = $aggregates->get($account->id);
            $dr = $agg ? (int) $agg->sum_debit : 0;
            $cr = $agg ? (int) $agg->sum_credit : 0;

            if ($dr === 0 && $cr === 0) {
                continue;
            }

            $normalAmount = $account->normal_balance === NormalBalance::DEBIT ? ($dr - $cr) : ($cr - $dr);
            $effective = $account->signedSectionAmount($normalAmount);

            $line = new FinancialStatementLine(
                accountId: $account->id,
                code: $account->code,
                name: $account->name,
                balance: $effective,
                isContra: $account->isContra(),
            );

            if ($account->type === AccountType::REVENUE) {
                $revenues[] = $line;
                $totalRevenue += $effective;
            } else {
                $expenses[] = $line;
                $totalExpense += $effective;
            }
        }

        $netIncome = $totalRevenue - $totalExpense;

        return new IncomeStatementReport(
            currency: $this->fxResolver->baseCurrencyCode(),
            precision: $this->fxResolver->basePrecision(),
            fromDate: $fromDate,
            toDate: $toDate,
            fiscalPeriodId: $fiscalPeriodId,
            costCenterId: $costCenterId,
            revenues: $revenues,
            expenses: $expenses,
            totalRevenue: $totalRevenue,
            totalExpense: $totalExpense,
            netIncome: $netIncome,
            isProfitable: $netIncome > 0,
        );
    }
}
