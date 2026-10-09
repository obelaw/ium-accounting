<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Data\FiscalYearCloseDto;
use Obelaw\Ium\Accounting\Data\JournalEntryLineDto;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Events\FiscalYearClosed;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\FiscalYear;

class ClosingService
{
    public function __construct(
        private readonly ?AccountService $accounts = null,
        private readonly ?JournalEntryService $journalEntries = null,
        private readonly ?FiscalPeriodService $fiscalPeriods = null,
        private readonly ?AuditService $audit = null
    ) {}

    private function accountService(): AccountService
    {
        return $this->accounts ?? app(AccountService::class);
    }

    private function journalEntryService(): JournalEntryService
    {
        return $this->journalEntries ?? app(JournalEntryService::class);
    }

    private function fiscalPeriodService(): FiscalPeriodService
    {
        return $this->fiscalPeriods ?? app(FiscalPeriodService::class);
    }

    private function auditService(): AuditService
    {
        return $this->audit ?? app(AuditService::class);
    }

    /**
     * Perform the formal Fiscal Year-End Closing Workflow:
     * - Zeroes out nominal accounts (Revenues & Expenses)
     * - Transfers Net Income/Loss to Retained Earnings
     * - Locks the fiscal year and its underlying periods
     */
    public function closeFiscalYear(FiscalYearCloseDto $dto): FiscalYear
    {
        return DB::transaction(function () use ($dto) {
            $year = FiscalYear::with('periods')->findOrFail($dto->fiscalYearId);

            if ($year->status === FiscalPeriodStatus::LOCKED) {
                throw new InvalidArgumentException(sprintf('Fiscal Year "%s" is already locked and closed.', $year->name));
            }

            // Resolve retained earnings and income summary accounts
            $retainedCode = $dto->retainedEarningsAccountCode
                ?? Config::get('ium.accounting.closing.retained_earnings_account_code', '3100');
            $summaryCode = $dto->incomeSummaryAccountCode
                ?? Config::get('ium.accounting.closing.income_summary_account_code', '3900');

            $retainedAccount = $this->accountService()->findByCodeOrFail($retainedCode);
            $summaryAccount = $this->accountService()->findByCodeOrFail($summaryCode);

            // Fetch nominal accounts with non-zero balances
            $nominalAccounts = Account::postingAccounts()
                ->whereIn('type', [AccountType::REVENUE->value, AccountType::EXPENSE->value])
                ->where('current_balance', '!=', 0.0)
                ->get();

            $closingLines = [];
            $totalRevenue = 0.0;
            $totalExpense = 0.0;

            foreach ($nominalAccounts as $acc) {
                $bal = abs($acc->current_balance);

                if ($acc->type === AccountType::REVENUE) {
                    $totalRevenue += $bal;
                    // Zero revenue: Debit Revenue account
                    $closingLines[] = new JournalEntryLineDto(
                        accountId: $acc->id,
                        type: PostingSide::DEBIT,
                        amount: $bal,
                        narration: sprintf('Year-end nominal closing for %s', $acc->name)
                    );
                } elseif ($acc->type === AccountType::EXPENSE) {
                    $totalExpense += $bal;
                    // Zero expense: Credit Expense account
                    $closingLines[] = new JournalEntryLineDto(
                        accountId: $acc->id,
                        type: PostingSide::CREDIT,
                        amount: $bal,
                        narration: sprintf('Year-end nominal closing for %s', $acc->name)
                    );
                }
            }

            // Balance the closing lines against Retained Earnings (or Income Summary)
            $netIncome = $totalRevenue - $totalExpense;

            if ($netIncome > 0) {
                // Net Profit: Credit Retained Earnings
                $closingLines[] = new JournalEntryLineDto(
                    accountId: $retainedAccount->id,
                    type: PostingSide::CREDIT,
                    amount: $netIncome,
                    narration: sprintf('Net Profit transfer to Retained Earnings for %s', $year->name)
                );
            } elseif ($netIncome < 0) {
                // Net Loss: Debit Retained Earnings
                $closingLines[] = new JournalEntryLineDto(
                    accountId: $retainedAccount->id,
                    type: PostingSide::DEBIT,
                    amount: abs($netIncome),
                    narration: sprintf('Net Loss transfer from Retained Earnings for %s', $year->name)
                );
            }

            // Create closing journal entry if any nominal accounts had balances
            $closingDate = $dto->closingDate ?? Carbon::parse($year->end_date)->format('Y-m-d');
            $closingPeriod = $this->fiscalPeriodService()->findPeriodForDate($closingDate);

            $closingEntryNumber = $this->journalEntryService()->generateNextEntryNumber('CLOSE');

            $closingEntry = null;
            if (!empty($closingLines)) {
                $closingDto = new CreateJournalEntryDto(
                    entryDate: $closingDate,
                    lines: $closingLines,
                    entryNumber: $closingEntryNumber,
                    fiscalPeriodId: $closingPeriod?->id,
                    entryType: JournalEntryType::CLOSING,
                    narration: sprintf('Fiscal Year %s Nominal Account Closing Entry', $year->name),
                    creatorId: $dto->actorId,
                    sourceType: FiscalYear::class,
                    sourceId: $year->id
                );

                $closingEntry = $this->journalEntryService()->createDraft($closingDto);
                $closingEntry = $this->journalEntryService()->post($closingEntry->id, $dto->actorId);
            }

            // Seal all periods under this fiscal year
            foreach ($year->periods as $period) {
                if ($period->status !== FiscalPeriodStatus::LOCKED) {
                    $period->update([
                        'status' => FiscalPeriodStatus::LOCKED,
                        'locked_at' => now(),
                        'locked_by' => $dto->actorId,
                        'lock_reason' => sprintf('Year-end closing of %s', $year->name),
                    ]);
                }
            }

            // Lock fiscal year
            $beforeYear = $year->toArray();
            $year->update([
                'status' => FiscalPeriodStatus::LOCKED,
                'closed_at' => now(),
                'closed_by' => $dto->actorId,
            ]);

            $this->auditService()->record(
                action: AuditAction::YEAR_END_CLOSED,
                auditable: $year,
                before: $beforeYear,
                after: $year->fresh()->toArray(),
                actorId: $dto->actorId,
                reason: $dto->notes ?? 'Year-end close completed',
                originContext: 'ClosingService::closeFiscalYear'
            );

            if ($closingEntry) {
                event(new FiscalYearClosed($year, $closingEntry, $dto->actorId));
            }

            return $year->fresh();
        });
    }
}
