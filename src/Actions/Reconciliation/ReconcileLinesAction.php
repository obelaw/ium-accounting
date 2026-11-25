<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Reconciliation;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Obelaw\Ium\Accounting\Actions\JournalEntry\CreateDraftEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\PostJournalEntryAction;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Data\ReconcileLinesData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Enums\ReconciliationStatus;
use Obelaw\Ium\Accounting\Events\LinesReconciled;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\ReconciliationException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\JournalEntryLine;
use Obelaw\Ium\Accounting\Models\Reconciliation;
use Obelaw\Ium\Accounting\Models\ReconciliationAllocation;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;
use Obelaw\Ium\Facades\Ium;

/**
 * Match open debit and credit journal lines on a reconcilable account (AR/AP/Clearing).
 * Supports partial & full matching and automatically posts Realized FX Gain/Loss when
 * foreign-currency items settle at a different exchange rate.
 *
 * @extends Action<ReconcileLinesData, Reconciliation>
 */
final class ReconcileLinesAction extends Action
{
    protected ?string $ability = 'accounting.reconciliation.create';

    public function __construct(
        private readonly CreateDraftEntryAction $createDraft,
        private readonly PostJournalEntryAction $postEntry,
        private readonly ExchangeRateResolver $fxResolver,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): Reconciliation
    {
        $debitLines = JournalEntryLine::with(['journalEntry', 'account'])
            ->whereIn('id', $input->debitLineIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $creditLines = JournalEntryLine::with(['journalEntry', 'account'])
            ->whereIn('id', $input->creditLineIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($debitLines->count() !== count(array_unique($input->debitLineIds))
            || $creditLines->count() !== count(array_unique($input->creditLineIds))) {
            throw ReconciliationException::invalid('One or more journal lines specified for reconciliation were not found.');
        }

        $firstLine = $debitLines->first();
        $accountId = $firstLine->account_id;
        $account = $firstLine->account;
        $currency = $firstLine->journalEntry->currency;

        if (! $account->is_reconcilable) {
            throw ReconciliationException::invalid("Account [{$account->code}] is not marked as reconcilable.");
        }

        foreach ($debitLines as $line) {
            $this->assertLineEligible($line, PostingSide::DEBIT, $accountId, $currency);
        }
        foreach ($creditLines as $line) {
            $this->assertLineEligible($line, PostingSide::CREDIT, $accountId, $currency);
        }

        $totalDebitOpen = $debitLines->sum(static fn (JournalEntryLine $l): int => $l->residualAmount());
        $totalCreditOpen = $creditLines->sum(static fn (JournalEntryLine $l): int => $l->residualAmount());
        $maxMatchable = min($totalDebitOpen, $totalCreditOpen);

        if ($maxMatchable <= 0) {
            throw ReconciliationException::invalid('No open residual balance available to reconcile.');
        }

        $targetAmount = $input->amount !== null ? min($input->amount, $maxMatchable) : $maxMatchable;

        $year = Carbon::parse($input->reconciledDate)->format('Y');
        $recNumber = Ium::sequence()->next("accounting.rec.{$year}", "REC-{$year}", 6);

        $reconciliation = Reconciliation::create([
            'reconciliation_number' => $recNumber,
            'account_id' => $accountId,
            'party_type' => $firstLine->party_type,
            'party_id' => $firstLine->party_id,
            'reconciled_date' => $input->reconciledDate,
            'currency' => $currency,
            'total_amount' => $targetAmount,
            'actor_id' => $actor->id,
            'notes' => $input->notes,
        ]);

        // FIFO allocation across debit lines and credit lines
        $remainingToMatch = $targetAmount;
        $totalFxDiff = 0;

        $dIdx = 0;
        $cIdx = 0;
        $debitList = $debitLines->values()->all();
        $creditList = $creditLines->values()->all();

        while ($remainingToMatch > 0 && $dIdx < count($debitList) && $cIdx < count($creditList)) {
            /** @var JournalEntryLine $dLine */
            $dLine = $debitList[$dIdx];
            /** @var JournalEntryLine $cLine */
            $cLine = $creditList[$cIdx];

            $dRem = $dLine->residualAmount();
            $cRem = $cLine->residualAmount();

            if ($dRem <= 0) {
                $dIdx++;

                continue;
            }
            if ($cRem <= 0) {
                $cIdx++;

                continue;
            }

            $matched = min($remainingToMatch, $dRem, $cRem);

            // Compute exact proportional functional amounts (absorbing any final remainder when closing line)
            $dFunc = ($matched === $dRem)
                ? $dLine->residualFunctional()
                : (int) round(($dLine->residualFunctional() * $matched) / $dRem, 0, PHP_ROUND_HALF_UP);

            $cFunc = ($matched === $cRem)
                ? $cLine->residualFunctional()
                : (int) round(($cLine->residualFunctional() * $matched) / $cRem, 0, PHP_ROUND_HALF_UP);

            $fxDiff = $cFunc - $dFunc;
            $totalFxDiff += $fxDiff;

            ReconciliationAllocation::create([
                'reconciliation_id' => $reconciliation->id,
                'debit_line_id' => $dLine->id,
                'credit_line_id' => $cLine->id,
                'amount' => $matched,
                'amount_functional_debit' => $dFunc,
                'amount_functional_credit' => $cFunc,
                'fx_difference' => $fxDiff,
            ]);

            $this->applyLineReconciliation($dLine, $matched, $dFunc);
            $this->applyLineReconciliation($cLine, $matched, $cFunc);

            $remainingToMatch -= $matched;

            if ($dLine->residualAmount() === 0) {
                $dIdx++;
            }
            if ($cLine->residualAmount() === 0) {
                $cIdx++;
            }
        }

        // Automatically post Realized FX Gain/Loss when foreign currency debit & credit functional totals differ
        if ($input->postFxDifference && $totalFxDiff !== 0) {
            $fxEntry = $this->postRealizedFxEntry(
                account: $account,
                fxDiff: $totalFxDiff,
                reconciledDate: $input->reconciledDate,
                reconciliation: $reconciliation,
                partyType: $firstLine->party_type,
                partyId: $firstLine->party_id,
                actorId: $actor->id,
            );

            $reconciliation->update(['fx_gain_loss_entry_id' => $fxEntry->id]);
        }

        $fresh = $reconciliation->fresh(['allocations', 'fxGainLossEntry']);

        $this->audit->record(
            action: AuditAction::RECONCILED,
            auditable: $fresh,
            after: $fresh->toArray(),
            actorId: $actor->id,
            reason: $input->notes,
            originContext: self::class,
        );

        $this->recordEvent(new LinesReconciled($fresh, $actor->id));

        return $fresh;
    }

    private function assertLineEligible(JournalEntryLine $line, PostingSide $expectedSide, int $accountId, string $currency): void
    {
        if ($line->type !== $expectedSide) {
            throw ReconciliationException::invalid("Line #{$line->id} must be a {$expectedSide->value} line.");
        }

        if ($line->account_id !== $accountId) {
            throw ReconciliationException::invalid('All reconciled lines must belong to the same reconcilable account.');
        }

        if ($line->journalEntry->status !== JournalEntryStatus::POSTED) {
            throw ReconciliationException::invalid("Line #{$line->id} belongs to an unposted voucher.");
        }

        if ($line->journalEntry->currency !== $currency) {
            throw ReconciliationException::invalid('All reconciled lines must share the same transaction currency.');
        }

        if ($line->residualAmount() <= 0) {
            throw ReconciliationException::invalid("Line #{$line->id} is already fully reconciled.");
        }
    }

    private function applyLineReconciliation(JournalEntryLine $line, int $matchedAmount, int $matchedFunctional): void
    {
        $newReconciled = (int) $line->reconciled_amount + $matchedAmount;
        $newReconciledFunc = (int) $line->reconciled_functional + $matchedFunctional;
        $status = $newReconciled >= (int) $line->amount
            ? ReconciliationStatus::RECONCILED
            : ReconciliationStatus::PARTIAL;

        $line->update([
            'reconciled_amount' => $newReconciled,
            'reconciled_functional' => $newReconciledFunc,
            'reconciliation_status' => $status,
        ]);

        $line->reconciled_amount = $newReconciled;
        $line->reconciled_functional = $newReconciledFunc;
        $line->reconciliation_status = $status;
    }

    private function postRealizedFxEntry(
        Account $account,
        int $fxDiff,
        string $reconciledDate,
        Reconciliation $reconciliation,
        ?string $partyType,
        ?int $partyId,
        string $actorId,
    ): \Obelaw\Ium\Accounting\Models\JournalEntry {
        $baseCurrency = $this->fxResolver->baseCurrencyCode();
        $absDiff = abs($fxDiff);

        // fxDiff = creditFunc - debitFunc
        // If fxDiff > 0: credit side has MORE functional value than debit side -> Dr Account, Cr Realized FX Gain
        // If fxDiff < 0: debit side has MORE functional value than credit side -> Dr Realized FX Loss, Cr Account
        if ($fxDiff > 0) {
            $gainCode = (string) Config::get('ium.accounting.accounts.fx_realized_gain', '4910');
            $fxAccount = Account::where('code', $gainCode)->first()
                ?? throw AccountNotFoundException::forCode($gainCode);

            $lines = [
                new JournalLineData(
                    accountId: $account->id,
                    type: PostingSide::DEBIT,
                    amount: $absDiff,
                    amountFunctional: $absDiff,
                    narration: "Realized FX Gain on {$reconciliation->reconciliation_number}",
                    partyType: $partyType,
                    partyId: $partyId,
                ),
                new JournalLineData(
                    accountId: $fxAccount->id,
                    type: PostingSide::CREDIT,
                    amount: $absDiff,
                    amountFunctional: $absDiff,
                    narration: "Realized FX Gain on {$reconciliation->reconciliation_number}",
                ),
            ];
        } else {
            $lossCode = (string) Config::get('ium.accounting.accounts.fx_realized_loss', '5910');
            $fxAccount = Account::where('code', $lossCode)->first()
                ?? throw AccountNotFoundException::forCode($lossCode);

            $lines = [
                new JournalLineData(
                    accountId: $fxAccount->id,
                    type: PostingSide::DEBIT,
                    amount: $absDiff,
                    amountFunctional: $absDiff,
                    narration: "Realized FX Loss on {$reconciliation->reconciliation_number}",
                ),
                new JournalLineData(
                    accountId: $account->id,
                    type: PostingSide::CREDIT,
                    amount: $absDiff,
                    amountFunctional: $absDiff,
                    narration: "Realized FX Loss on {$reconciliation->reconciliation_number}",
                    partyType: $partyType,
                    partyId: $partyId,
                ),
            ];
        }

        $draft = $this->createDraft->execute(new CreateJournalEntryData(
            entryDate: $reconciledDate,
            lines: $lines,
            entryType: JournalEntryType::ADJUSTING,
            reference: $reconciliation->reconciliation_number,
            narration: "Realized FX difference for reconciliation {$reconciliation->reconciliation_number}",
            currency: $baseCurrency,
            exchangeRate: 1.0,
            creatorId: $actorId,
            sourceType: Reconciliation::class,
            sourceId: $reconciliation->id,
        ));

        return $this->postEntry->execute([
            'entry_id' => $draft->id,
            'actor_id' => $actorId,
            'bypass_approval' => true,
        ]);
    }
}
