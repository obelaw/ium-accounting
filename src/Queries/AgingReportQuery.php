<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Queries;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Enums\ReconciliationStatus;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\JournalEntryLine;
use Obelaw\Ium\Accounting\Reports\AgingBucketRow;
use Obelaw\Ium\Accounting\Reports\AgingReport;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;

/**
 * Accounts Receivable (AR) / Accounts Payable (AP) Aging Report
 * bucketed into: Current, 1–30, 31–60, 61–90, and 90+ days overdue.
 */
final class AgingReportQuery
{
    public function __construct(private readonly ExchangeRateResolver $fxResolver) {}

    public function get(
        int $accountId,
        ?string $asOfDate = null,
        ?string $partyType = null,
    ): AgingReport {
        $account = Account::find($accountId) ?? throw AccountNotFoundException::forId($accountId);
        $asOf = Carbon::parse($asOfDate ?? date('Y-m-d'))->startOfDay();
        $asOfStr = $asOf->format('Y-m-d');

        $query = JournalEntryLine::with('journalEntry')
            ->where('account_id', $account->id)
            ->whereIn('reconciliation_status', [ReconciliationStatus::OPEN->value, ReconciliationStatus::PARTIAL->value])
            ->whereHas('journalEntry', static function ($q) use ($asOfStr): void {
                $q->where('status', JournalEntryStatus::POSTED->value)
                    ->whereDate('entry_date', '<=', $asOfStr);
            });

        if ($partyType !== null) {
            $query->where('party_type', $partyType);
        }

        $openLines = $query->get();

        /** @var array<string, array{party_type: string, party_id: int, current: int, d1_30: int, d31_60: int, d61_90: int, d90p: int}> $grouped */
        $grouped = [];

        foreach ($openLines as $line) {
            $residual = $line->residualFunctional();
            if ($residual <= 0) {
                continue;
            }

            // Positive if line matches account's normal balance (e.g. Debit on AR, Credit on AP), negative for unapplied credit/debit memos
            $isNormalSide = ($account->normal_balance === NormalBalance::DEBIT && $line->type === PostingSide::DEBIT)
                || ($account->normal_balance === NormalBalance::CREDIT && $line->type === PostingSide::CREDIT);
            $signedResidual = $isNormalSide ? $residual : -$residual;

            $pType = $line->party_type ?? 'unassigned';
            $pId = (int) ($line->party_id ?? 0);
            $key = "{$pType}:{$pId}";

            $grouped[$key] ??= [
                'party_type' => $pType,
                'party_id' => $pId,
                'current' => 0,
                'd1_30' => 0,
                'd31_60' => 0,
                'd61_90' => 0,
                'd90p' => 0,
            ];

            $referenceDate = ($line->due_date ?? $line->journalEntry->entry_date)->copy()->startOfDay();
            $daysPastDue = (int) $referenceDate->diffInDays($asOf, false);

            if ($daysPastDue <= 0) {
                $grouped[$key]['current'] += $signedResidual;
            } elseif ($daysPastDue <= 30) {
                $grouped[$key]['d1_30'] += $signedResidual;
            } elseif ($daysPastDue <= 60) {
                $grouped[$key]['d31_60'] += $signedResidual;
            } elseif ($daysPastDue <= 90) {
                $grouped[$key]['d61_90'] += $signedResidual;
            } else {
                $grouped[$key]['d90p'] += $signedResidual;
            }
        }

        $rows = [];
        $totCur = 0;
        $tot1 = 0;
        $tot31 = 0;
        $tot61 = 0;
        $tot90 = 0;

        foreach ($grouped as $g) {
            $rowTotal = $g['current'] + $g['d1_30'] + $g['d31_60'] + $g['d61_90'] + $g['d90p'];
            if ($rowTotal === 0) {
                continue;
            }

            $totCur += $g['current'];
            $tot1 += $g['d1_30'];
            $tot31 += $g['d31_60'];
            $tot61 += $g['d61_90'];
            $tot90 += $g['d90p'];

            $rows[] = new AgingBucketRow(
                partyType: $g['party_type'],
                partyId: $g['party_id'],
                current: $g['current'],
                days1To30: $g['d1_30'],
                days31To60: $g['d31_60'],
                days61To90: $g['d61_90'],
                daysOver90: $g['d90p'],
                totalOpen: $rowTotal,
            );
        }

        return new AgingReport(
            asOfDate: $asOfStr,
            currency: $this->fxResolver->baseCurrencyCode(),
            accountId: $account->id,
            accountCode: $account->code,
            rows: $rows,
            totalCurrent: $totCur,
            totalDays1To30: $tot1,
            totalDays31To60: $tot31,
            totalDays61To90: $tot61,
            totalDaysOver90: $tot90,
            grandTotal: $totCur + $tot1 + $tot31 + $tot61 + $tot90,
        );
    }
}
