<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Queries;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Reports\BalanceSheetReport;
use Obelaw\Ium\Accounting\Reports\FinancialStatementLine;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;

/**
 * Point-in-time Statutory Balance Sheet (`asOfDate`).
 * Computes directly from immutable GL postings so historical Balance Sheets
 * remain mathematically exact (Assets == Liabilities + Equity + Current Earnings).
 */
final class BalanceSheetQuery
{
    public function __construct(
        private readonly IncomeStatementQuery $incomeStatement,
        private readonly ExchangeRateResolver $fxResolver,
    ) {}

    public function get(?string $asOfDate = null): BalanceSheetReport
    {
        $cutoff = $asOfDate !== null ? Carbon::parse($asOfDate)->format('Y-m-d') : null;

        $accounts = Account::postingAccounts()
            ->whereIn('type', [AccountType::ASSET->value, AccountType::LIABILITY->value, AccountType::EQUITY->value])
            ->orderBy('code')
            ->get();

        $query = GeneralLedgerPosting::query()
            ->selectRaw('account_id, COALESCE(SUM(debit), 0) as sum_debit, COALESCE(SUM(credit), 0) as sum_credit')
            ->whereIn('account_id', $accounts->pluck('id'))
            ->groupBy('account_id');

        if ($cutoff !== null) {
            $query->whereDate('posting_date', '<=', $cutoff);
        }

        $aggregates = $query->get()->keyBy('account_id');

        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

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

            match ($account->type) {
                AccountType::ASSET => [$assets[] = $line, $totalAssets += $effective],
                AccountType::LIABILITY => [$liabilities[] = $line, $totalLiabilities += $effective],
                AccountType::EQUITY => [$equity[] = $line, $totalEquity += $effective],
                default => null,
            };
        }

        $pnl = $this->incomeStatement->get(toDate: $cutoff);
        $currentEarnings = $pnl->netIncome;
        $totalEquityWithEarnings = $totalEquity + $currentEarnings;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquityWithEarnings;
        $discrepancy = abs($totalAssets - $totalLiabilitiesAndEquity);

        return new BalanceSheetReport(
            currency: $this->fxResolver->baseCurrencyCode(),
            precision: $this->fxResolver->basePrecision(),
            asOfDate: $cutoff,
            assets: $assets,
            liabilities: $liabilities,
            equity: $equity,
            totalAssets: $totalAssets,
            totalLiabilities: $totalLiabilities,
            totalEquity: $totalEquity,
            currentEarnings: $currentEarnings,
            totalEquityWithEarnings: $totalEquityWithEarnings,
            totalLiabilitiesAndEquity: $totalLiabilitiesAndEquity,
            isBalanced: $discrepancy === 0,
            discrepancy: $discrepancy,
        );
    }
}
