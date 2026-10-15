<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Models\JournalEntry;

class LedgerService
{
    /**
     * Atomically record postings to the General Ledger and update account balances.
     */
    public function postEntryToLedger(JournalEntry $entry, string $actorId): void
    {
        DB::transaction(function () use ($entry) {
            foreach ($entry->lines as $line) {
                $account = Account::findOrFail($line->account_id);

                $debit = $line->type === PostingSide::DEBIT ? (int) $line->amount_functional : 0;
                $credit = $line->type === PostingSide::CREDIT ? (int) $line->amount_functional : 0;

                // Determine change based on normal balance
                $delta = $account->normal_balance === NormalBalance::DEBIT
                    ? ($debit - $credit)
                    : ($credit - $debit);

                $newBalance = (int) ($account->current_balance + $delta);

                // Write immutable GL posting record
                GeneralLedgerPosting::create([
                    'account_id' => $account->id,
                    'journal_entry_id' => $entry->id,
                    'journal_entry_line_id' => $line->id,
                    'fiscal_period_id' => $entry->fiscal_period_id,
                    'posting_date' => $entry->entry_date,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance_after' => $newBalance,
                    'narration' => $line->narration ?? $entry->narration,
                ]);

                // Update account cached balance
                $account->update(['current_balance' => $newBalance]);
            }
        });
    }

    /**
     * Retrieve the General Ledger statement for an account.
     *
     * @return Collection<int, GeneralLedgerPosting>
     */
    public function getAccountLedger(int $accountId, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $query = GeneralLedgerPosting::with('journalEntry')
            ->where('account_id', $accountId)
            ->orderBy('posting_date')
            ->orderBy('id');

        if ($fromDate !== null) {
            $query->where('posting_date', '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->where('posting_date', '<=', $toDate);
        }

        return $query->get();
    }

    /**
     * Generate the Trial Balance report.
     * Enforces the zero-sum ledger balance identity: Total Debits == Total Credits.
     */
    public function generateTrialBalance(?int $fiscalPeriodId = null): array
    {
        $accounts = Account::postingAccounts()->orderBy('code')->get();

        $rows = [];
        $grandDebit = 0;
        $grandCredit = 0;

        foreach ($accounts as $account) {
            $postingsQuery = $account->ledgerPostings();
            if ($fiscalPeriodId !== null) {
                $postingsQuery->where('fiscal_period_id', $fiscalPeriodId);
            }

            $totalDebit = (int) $postingsQuery->sum('debit');
            $totalCredit = (int) $postingsQuery->sum('credit');

            if ($totalDebit === 0 && $totalCredit === 0 && (int) $account->current_balance === 0) {
                continue;
            }

            $netBalance = $account->normal_balance === NormalBalance::DEBIT
                ? ($totalDebit - $totalCredit)
                : ($totalCredit - $totalDebit);

            $debitColumn = $account->normal_balance === NormalBalance::DEBIT ? max(0, $netBalance) : 0;
            $creditColumn = $account->normal_balance === NormalBalance::CREDIT ? max(0, $netBalance) : 0;

            $grandDebit += $debitColumn;
            $grandCredit += $creditColumn;

            $rows[] = [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'type' => $account->type->value,
                'normal_balance' => $account->normal_balance->value,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'debit_balance' => $debitColumn,
                'credit_balance' => $creditColumn,
                'total_debit_major' => round($totalDebit / 100, 2),
                'total_credit_major' => round($totalCredit / 100, 2),
                'debit_balance_major' => round($debitColumn / 100, 2),
                'credit_balance_major' => round($creditColumn / 100, 2),
            ];
        }

        $imbalance = abs($grandDebit - $grandCredit);

        return [
            'accounts' => $rows,
            'total_debit' => $grandDebit,
            'total_credit' => $grandCredit,
            'total_debit_major' => round($grandDebit / 100, 2),
            'total_credit_major' => round($grandCredit / 100, 2),
            'is_balanced' => $imbalance === 0,
            'imbalance' => $imbalance,
        ];
    }

    /**
     * Generate the Balance Sheet (Assets, Liabilities, Equity).
     * Enforces the fundamental accounting equation: Assets = Liabilities + Equity.
     */
    public function generateBalanceSheet(): array
    {
        $accounts = Account::postingAccounts()->get();

        $assets = [];
        $liabilities = [];
        $equity = [];

        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        foreach ($accounts as $account) {
            $bal = (int) $account->current_balance;

            match ($account->type) {
                AccountType::ASSET => [
                    $assets[] = ['code' => $account->code, 'name' => $account->name, 'balance' => $bal],
                    $totalAssets += $bal,
                ],
                AccountType::LIABILITY => [
                    $liabilities[] = ['code' => $account->code, 'name' => $account->name, 'balance' => $bal],
                    $totalLiabilities += $bal,
                ],
                AccountType::EQUITY => [
                    $equity[] = ['code' => $account->code, 'name' => $account->name, 'balance' => $bal],
                    $totalEquity += $bal,
                ],
                default => null,
            };
        }

        $pnl = $this->generateIncomeStatement();
        $netIncome = $pnl['net_income'];
        $totalEquityWithIncome = $totalEquity + $netIncome;

        $balanceDifference = abs($totalAssets - ($totalLiabilities + $totalEquityWithIncome));

        return [
            'assets' => [
                'items' => $assets,
                'total' => $totalAssets,
                'total_major' => round($totalAssets / 100, 2),
            ],
            'liabilities' => [
                'items' => $liabilities,
                'total' => $totalLiabilities,
                'total_major' => round($totalLiabilities / 100, 2),
            ],
            'equity' => [
                'items' => $equity,
                'total' => $totalEquity,
                'total_major' => round($totalEquity / 100, 2),
                'current_year_earnings' => $netIncome,
                'current_year_earnings_major' => round($netIncome / 100, 2),
                'total_with_earnings' => $totalEquityWithIncome,
                'total_with_earnings_major' => round($totalEquityWithIncome / 100, 2),
            ],
            'total_liabilities_and_equity' => $totalLiabilities + $totalEquityWithIncome,
            'total_liabilities_and_equity_major' => round(($totalLiabilities + $totalEquityWithIncome) / 100, 2),
            'is_balanced' => $balanceDifference === 0,
            'discrepancy' => $balanceDifference,
        ];
    }

    /**
     * Generate the Income Statement (P&L: Revenues - Expenses = Net Income).
     */
    public function generateIncomeStatement(?int $fiscalPeriodId = null): array
    {
        $accounts = Account::postingAccounts()
            ->whereIn('type', [AccountType::REVENUE->value, AccountType::EXPENSE->value])
            ->get();

        $revenues = [];
        $expenses = [];

        $totalRevenue = 0;
        $totalExpense = 0;

        foreach ($accounts as $account) {
            $bal = (int) $account->current_balance;

            if ($account->type === AccountType::REVENUE) {
                $revenues[] = ['code' => $account->code, 'name' => $account->name, 'balance' => $bal];
                $totalRevenue += $bal;
            } elseif ($account->type === AccountType::EXPENSE) {
                $expenses[] = ['code' => $account->code, 'name' => $account->name, 'balance' => $bal];
                $totalExpense += $bal;
            }
        }

        $netIncome = $totalRevenue - $totalExpense;

        return [
            'revenues' => [
                'items' => $revenues,
                'total' => $totalRevenue,
                'total_major' => round($totalRevenue / 100, 2),
            ],
            'expenses' => [
                'items' => $expenses,
                'total' => $totalExpense,
                'total_major' => round($totalExpense / 100, 2),
            ],
            'net_income' => $netIncome,
            'net_income_major' => round($netIncome / 100, 2),
            'is_profitable' => $netIncome > 0,
        ];
    }
}
