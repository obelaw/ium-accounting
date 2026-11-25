<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Closing;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Actions\JournalEntry\CreateDraftEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\PostJournalEntryAction;
use Obelaw\Ium\Accounting\Data\CloseFiscalYearData;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Events\FiscalYearClosed;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FiscalYear;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;
use Obelaw\Ium\Facades\Ium;

/**
 * Statutory Fiscal Year-End Closing Action.
 *
 * Scopes nominal accounts strictly to the target Fiscal Year's postings, handles
 * contra-revenue & contra-expense balances accurately using signed nets, sweeps
 * net profit/loss into Retained Earnings, and locks the year and all its periods.
 *
 * @extends Action<CloseFiscalYearData, FiscalYear>
 */
final class CloseFiscalYearAction extends Action
{
    protected ?string $ability = 'accounting.fiscal_year.close';

    public function __construct(
        private readonly CreateDraftEntryAction $createDraft,
        private readonly PostJournalEntryAction $postEntry,
        private readonly ExchangeRateResolver $fxResolver,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): FiscalYear
    {
        $year = FiscalYear::with('periods')->lockForUpdate()->findOrFail($input->fiscalYearId);

        if ($year->status === FiscalPeriodStatus::LOCKED) {
            throw new InvalidArgumentException("Fiscal Year [{$year->name}] is already locked and closed.");
        }

        $retainedCode = $input->retainedEarningsAccountCode
            ?? (string) Config::get('ium.accounting.accounts.retained_earnings', '3100');

        $retainedAccount = Account::where('code', $retainedCode)->first()
            ?? throw AccountNotFoundException::forCode($retainedCode);

        $nominalAccounts = Account::postingAccounts()
            ->whereIn('type', [AccountType::REVENUE->value, AccountType::EXPENSE->value])
            ->orderBy('code')
            ->get()
            ->keyBy('id');

        $periodIds = $year->periods->pluck('id')->all();
        $yearStart = Carbon::parse($year->start_date)->format('Y-m-d');
        $yearEnd = Carbon::parse($year->end_date)->format('Y-m-d');

        $aggregates = GeneralLedgerPosting::query()
            ->selectRaw('account_id, COALESCE(SUM(debit), 0) as sum_debit, COALESCE(SUM(credit), 0) as sum_credit')
            ->whereIn('account_id', $nominalAccounts->keys())
            ->where(function ($query) use ($periodIds, $yearStart, $yearEnd): void {
                if ($periodIds !== []) {
                    $query->whereIn('fiscal_period_id', $periodIds)
                        ->orWhere(function ($q) use ($yearStart, $yearEnd): void {
                            $q->whereNull('fiscal_period_id')
                                ->whereDate('posting_date', '>=', $yearStart)
                                ->whereDate('posting_date', '<=', $yearEnd);
                        });
                } else {
                    $query->whereDate('posting_date', '>=', $yearStart)
                        ->whereDate('posting_date', '<=', $yearEnd);
                }
            })
            ->groupBy('account_id')
            ->get();

        $closingLines = [];
        $closingDebits = 0;
        $closingCredits = 0;

        foreach ($aggregates as $agg) {
            /** @var Account|null $acc */
            $acc = $nominalAccounts->get((int) $agg->account_id);
            if ($acc === null) {
                continue;
            }

            $signedNet = (int) $agg->sum_debit - (int) $agg->sum_credit;
            if ($signedNet === 0) {
                continue;
            }

            if ($signedNet < 0) {
                $amount = abs($signedNet);
                $closingDebits += $amount;
                $closingLines[] = new JournalLineData($acc->id, PostingSide::DEBIT, $amount, narration: "Year-end closing for {$acc->name}");
            } else {
                $closingCredits += $signedNet;
                $closingLines[] = new JournalLineData($acc->id, PostingSide::CREDIT, $signedNet, narration: "Year-end closing for {$acc->name}");
            }
        }

        $netIncome = $closingDebits - $closingCredits;

        if ($netIncome > 0) {
            $closingLines[] = new JournalLineData($retainedAccount->id, PostingSide::CREDIT, $netIncome, narration: "Net Profit transfer for {$year->name}");
        } elseif ($netIncome < 0) {
            $closingLines[] = new JournalLineData($retainedAccount->id, PostingSide::DEBIT, abs($netIncome), narration: "Net Loss transfer for {$year->name}");
        }

        $closingDate = $input->closingDate ?? $yearEnd;
        $closingPeriodId = FiscalPeriod::whereDate('start_date', '<=', $closingDate)->whereDate('end_date', '>=', $closingDate)->value('id');
        $closingEntry = null;

        if ($closingLines !== []) {
            $closeYear = Carbon::parse($closingDate)->format('Y');
            $entryNumber = Ium::sequence()->next("accounting.close.{$closeYear}", "CLOSE-{$closeYear}", 6);

            $draft = $this->createDraft->execute(new CreateJournalEntryData(
                entryDate: $closingDate,
                lines: $closingLines,
                fiscalPeriodId: $closingPeriodId,
                entryNumber: $entryNumber,
                entryType: JournalEntryType::CLOSING,
                narration: "Fiscal Year {$year->name} Nominal Closing Entry",
                currency: $this->fxResolver->baseCurrencyCode(),
                exchangeRate: 1.0,
                creatorId: $actor->id,
                sourceType: FiscalYear::class,
                sourceId: $year->id,
            ));

            $closingEntry = $this->postEntry->execute([
                'entry_id' => $draft->id,
                'actor_id' => $actor->id,
                'bypass_approval' => true,
            ]);
        }

        foreach ($year->periods as $period) {
            if ($period->status !== FiscalPeriodStatus::LOCKED) {
                $period->update([
                    'status' => FiscalPeriodStatus::LOCKED,
                    'locked_at' => now(),
                    'locked_by' => $actor->id,
                    'lock_reason' => "Year-end closing of {$year->name}",
                ]);
            }
        }

        $beforeYear = $year->toArray();
        $year->update([
            'status' => FiscalPeriodStatus::LOCKED,
            'closed_at' => now(),
            'closed_by' => $actor->id,
        ]);

        $fresh = $year->fresh(['periods']);

        $this->audit->record(
            action: AuditAction::YEAR_END_CLOSED,
            auditable: $fresh,
            before: $beforeYear,
            after: $fresh->toArray(),
            actorId: $actor->id,
            reason: $input->notes ?? 'Year-end close completed',
            originContext: self::class,
        );

        $this->recordEvent(new FiscalYearClosed($fresh, $closingEntry, $actor->id));

        return $fresh;
    }
}
