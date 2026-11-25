<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Fx;

use Illuminate\Support\Facades\Config;
use Obelaw\Ium\Accounting\Actions\JournalEntry\CreateDraftEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\PostJournalEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\ReverseJournalEntryAction;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\FxRevaluationData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Reports\FxRevaluationResult;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;
use Obelaw\Ium\Support\Money;

/**
 * Period-end Unrealized Foreign Currency Revaluation (IAS 21 / ASC 830).
 *
 * Revalues open foreign-currency balance-sheet monetary accounts (Assets & Liabilities)
 * to the period-end closing rate and optionally posts a next-period auto-reversal.
 *
 * @extends Action<FxRevaluationData, FxRevaluationResult>
 */
final class RevalueOpenForeignBalancesAction extends Action
{
    protected ?string $ability = 'accounting.fx.revalue';

    public function __construct(
        private readonly CreateDraftEntryAction $createDraft,
        private readonly PostJournalEntryAction $postEntry,
        private readonly ReverseJournalEntryAction $reverseEntry,
        private readonly ExchangeRateResolver $fxResolver,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): FxRevaluationResult
    {
        $baseCurrency = $this->fxResolver->baseCurrencyCode();

        if ($input->currency === $baseCurrency) {
            return new FxRevaluationResult($input->currency, $input->closingRate, $input->asOfDate, 0, null, null);
        }

        $monetaryAccounts = Account::postingAccounts()
            ->whereIn('type', [AccountType::ASSET->value, AccountType::LIABILITY->value])
            ->get()
            ->keyBy('id');

        $aggregates = GeneralLedgerPosting::query()
            ->selectRaw('account_id, COALESCE(SUM(amount_currency), 0) as net_currency, COALESCE(SUM(debit - credit), 0) as net_functional')
            ->where('currency', $input->currency)
            ->whereDate('posting_date', '<=', $input->asOfDate)
            ->whereIn('account_id', $monetaryAccounts->keys())
            ->groupBy('account_id')
            ->get();

        $fromDecimals = Money::decimalsFor($input->currency);
        $toDecimals = Money::decimalsFor($baseCurrency);
        $scaleFactor = (10 ** $toDecimals) / (10 ** $fromDecimals);

        $lines = [];
        $netGainLoss = 0;

        $gainCode = $input->unrealizedGainAccountCode ?? (string) Config::get('ium.accounting.accounts.fx_unrealized_gain', '4920');
        $lossCode = $input->unrealizedLossAccountCode ?? (string) Config::get('ium.accounting.accounts.fx_unrealized_loss', '5920');

        foreach ($aggregates as $agg) {
            $netCurrency = (int) $agg->net_currency; // signed (+debit / -credit)
            $bookFunctional = (int) $agg->net_functional; // signed (+debit / -credit)

            if ($netCurrency === 0) {
                continue;
            }

            $revaluedFunctional = (int) round($netCurrency * $input->closingRate * $scaleFactor, 0, PHP_ROUND_HALF_UP);
            $diff = $revaluedFunctional - $bookFunctional; // positive => debit account / credit gain; negative => credit account / debit loss

            if ($diff === 0) {
                continue;
            }

            /** @var Account $account */
            $account = $monetaryAccounts->get((int) $agg->account_id);
            $netGainLoss += $diff;

            if ($diff > 0) {
                $gainAccount = Account::where('code', $gainCode)->first()
                    ?? throw AccountNotFoundException::forCode($gainCode);

                $lines[] = new JournalLineData(
                    accountId: $account->id,
                    type: PostingSide::DEBIT,
                    amount: $diff,
                    amountFunctional: $diff,
                    narration: "FX Revaluation ({$input->currency} @ {$input->closingRate}) - {$account->code}",
                );
                $lines[] = new JournalLineData(
                    accountId: $gainAccount->id,
                    type: PostingSide::CREDIT,
                    amount: $diff,
                    amountFunctional: $diff,
                    narration: "Unrealized FX Gain ({$input->currency}) - {$account->code}",
                );
            } else {
                $absDiff = abs($diff);
                $lossAccount = Account::where('code', $lossCode)->first()
                    ?? throw AccountNotFoundException::forCode($lossCode);

                $lines[] = new JournalLineData(
                    accountId: $lossAccount->id,
                    type: PostingSide::DEBIT,
                    amount: $absDiff,
                    amountFunctional: $absDiff,
                    narration: "Unrealized FX Loss ({$input->currency}) - {$account->code}",
                );
                $lines[] = new JournalLineData(
                    accountId: $account->id,
                    type: PostingSide::CREDIT,
                    amount: $absDiff,
                    amountFunctional: $absDiff,
                    narration: "FX Revaluation ({$input->currency} @ {$input->closingRate}) - {$account->code}",
                );
            }
        }

        if ($lines === []) {
            return new FxRevaluationResult($input->currency, $input->closingRate, $input->asOfDate, 0, null, null);
        }

        $draft = $this->createDraft->execute(new CreateJournalEntryData(
            entryDate: $input->asOfDate,
            lines: $lines,
            journalId: $input->journalId,
            entryType: JournalEntryType::FX_REVALUATION,
            narration: $input->narration ?? "Period-End FX Revaluation for {$input->currency} as of {$input->asOfDate} @ {$input->closingRate}",
            currency: $baseCurrency,
            exchangeRate: 1.0,
            creatorId: $actor->id,
        ));

        $posted = $this->postEntry->execute([
            'entry_id' => $draft->id,
            'actor_id' => $actor->id,
            'bypass_approval' => true,
        ]);

        $autoReversal = null;
        if ($input->autoReverseDate !== null) {
            $autoReversal = $this->reverseEntry->execute([
                'entry_id' => $posted->id,
                'reason' => "Auto-reversal of FX Revaluation {$posted->entry_number}",
                'actor_id' => $actor->id,
                'reversal_date' => $input->autoReverseDate,
            ]);
        }

        $this->audit->record(
            action: AuditAction::FX_REVALUED,
            auditable: $posted,
            after: ['currency' => $input->currency, 'closing_rate' => $input->closingRate, 'net_gain_loss' => $netGainLoss],
            actorId: $actor->id,
            originContext: self::class,
        );

        return new FxRevaluationResult(
            currency: $input->currency,
            closingRate: $input->closingRate,
            asOfDate: $input->asOfDate,
            netGainLoss: $netGainLoss,
            revaluationEntry: $posted->fresh(['lines']),
            autoReversalEntry: $autoReversal,
        );
    }
}
