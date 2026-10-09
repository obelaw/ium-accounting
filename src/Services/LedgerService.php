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

                $debit = $line->type === PostingSide::DEBIT ? $line->amount_functional : 0.0;
                $credit = $line->type === PostingSide::CREDIT ? $line->amount_functional : 0.0;

                // Determine change based on normal balance
                $delta = $account->normal_balance === NormalBalance::DEBIT
                    ? ($debit - $credit)
                    : ($credit - $debit);

                $newBalance = round($account->current_balance + $delta, 4);

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
        $grandDebit = 0.0;
        $grandCredit = 0.0;

        foreach ($accounts as $account) {
            $postingsQuery = $account->ledgerPostings();
            if ($fiscalPeriodId !== null) {
                $postingsQuery->where('fiscal_period_id', $fiscalPeriodId);
            }

            $totalDebit = (float) $postingsQuery->sum('debit');
            $totalCredit = (float) $postingsQuery->sum('credit');

            if ($totalDebit === 0.0 && $totalCredit === 0.0 && $account->current_balance === 0.0) {
                continue;
            }

            $netBalance = $account->normal_balance === NormalBalance::DEBIT
                ? ($totalDebit - $totalCredit)
                : ($totalCredit - $totalDebit);

            $debitColumn = $account->normal_balance === NormalBalance::DEBIT ? max(0.0, $netBalance) : 0.0;
            $creditColumn = $account->normal_balance === NormalBalance::CREDIT ? max(0.0, $netBalance) : 0.0;

            $grandDebit += $debitColumn;
            $grandCredit += $creditColumn;

            $rows[] = [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'type' => $account->type->value,
                'normal_balance' => $account->normal_balance->value,
                'total_debit' => round($totalDebit, 4),
                'total_credit' => round($totalCredit, 4),
                'debit_balance' => round($debitColumn, 4),
                'credit_balance' => round($creditColumn, 4),
            ];
        }

        $imbalance = abs($grandDebit - $grandCredit);

        return [
            'accounts' => $rows,
            'total_debit' => round($grandDebit, 4),
            'total_credit' => round($grandCredit, 4),
            'is_balanced' => $imbalance < 0.0001,
            'imbalance' => round($imbalance, 4),
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

        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        foreach ($accounts as $account) {
            $bal = $account->current_balance;

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

        $balanceDifference = abs($totalAssets - ($totalLiabilities + $totalEquity));

        return [
            'assets' => [
                'items' => $assets,
                'total' => round($totalAssets, 4),
            ],
            'liabilities' => [
                'items' => $liabilities,
                'total' => round($totalLiabilities, 4),
            ],
            'equity' => [
                'items' => $equity,
                'total' => round($totalEquity, 4),
            ],
            'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 4),
            'is_balanced' => $balanceDifference < 0.0001,
            'discrepancy' => round($balanceDifference, 4),
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

        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($accounts as $account) {
            $bal = $account->current_balance;

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
                'total' => round($totalRevenue, 4),
            ],
            'expenses' => [
                'items' => $expenses,
                'total' => round($totalExpense, 4),
            ],
            'net_income' => round($netIncome, 4),
            'is_profitable' => $netIncome > 0,
        ];
    }
}
