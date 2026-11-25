<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\FixedAsset;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Actions\JournalEntry\CreateDraftEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\PostJournalEntryAction;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\DisposeFixedAssetData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\DepreciationScheduleStatus;
use Obelaw\Ium\Accounting\Enums\FixedAssetStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\FixedAsset;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * Dispose of a Fixed Asset, skip remaining schedules, and automatically post
 * the balanced Gain/Loss on Disposal journal entry to the General Ledger:
 *
 *   Dr Proceeds Account (Cash/Bank/AR)       [proceeds]
 *   Dr Accumulated Depreciation              [accumulated_depreciation]
 *   Dr Loss on Disposal (if proceeds < NBV)  [loss]
 *      Cr Fixed Asset Account                [acquisition_cost]
 *      Cr Gain on Disposal (if proceeds > NBV) [gain]
 *
 * @extends Action<DisposeFixedAssetData, FixedAsset>
 */
final class DisposeFixedAssetAction extends Action
{
    protected ?string $ability = 'accounting.fixed_asset.dispose';

    public function __construct(
        private readonly CreateDraftEntryAction $createDraft,
        private readonly PostJournalEntryAction $postEntry,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): FixedAsset
    {
        $asset = FixedAsset::lockForUpdate()->findOrFail($input->assetId);

        if ($asset->status === FixedAssetStatus::DISPOSED) {
            throw new InvalidArgumentException("Fixed asset [{$asset->asset_code}] is already disposed.");
        }

        $before = $asset->toArray();

        $asset->schedules()
            ->where('status', DepreciationScheduleStatus::PENDING->value)
            ->update(['status' => DepreciationScheduleStatus::SKIPPED]);

        $disposalEntryId = null;

        if ($input->postDisposalEntry) {
            $lines = [];
            $narration = "Disposal of Fixed Asset {$asset->name} ({$asset->asset_code})";

            if ($input->proceedsMinor > 0) {
                $lines[] = new JournalLineData(
                    accountId: $input->proceedsAccountId,
                    type: PostingSide::DEBIT,
                    amount: $input->proceedsMinor,
                    costCenterId: $asset->cost_center_id,
                    narration: $narration,
                );
            }

            if ((int) $asset->accumulated_depreciation > 0) {
                $lines[] = new JournalLineData(
                    accountId: $asset->accumulated_depreciation_account_id,
                    type: PostingSide::DEBIT,
                    amount: (int) $asset->accumulated_depreciation,
                    costCenterId: $asset->cost_center_id,
                    narration: $narration,
                );
            }

            $lines[] = new JournalLineData(
                accountId: $asset->asset_account_id,
                type: PostingSide::CREDIT,
                amount: (int) $asset->acquisition_cost,
                costCenterId: $asset->cost_center_id,
                narration: $narration,
            );

            $gainOrLoss = $input->proceedsMinor - (int) $asset->net_book_value;

            if ($gainOrLoss > 0) {
                $gainAccountId = $input->gainAccountId ?? $asset->disposal_gain_account_id ?? $this->resolveSystemAccountId('asset_disposal_gain', '4930');
                $lines[] = new JournalLineData(
                    accountId: $gainAccountId,
                    type: PostingSide::CREDIT,
                    amount: $gainOrLoss,
                    costCenterId: $asset->cost_center_id,
                    narration: "Gain on disposal of {$asset->asset_code}",
                );
            } elseif ($gainOrLoss < 0) {
                $lossAccountId = $input->lossAccountId ?? $asset->disposal_loss_account_id ?? $this->resolveSystemAccountId('asset_disposal_loss', '5930');
                $lines[] = new JournalLineData(
                    accountId: $lossAccountId,
                    type: PostingSide::DEBIT,
                    amount: abs($gainOrLoss),
                    costCenterId: $asset->cost_center_id,
                    narration: "Loss on disposal of {$asset->asset_code}",
                );
            }

            $draft = $this->createDraft->execute(new CreateJournalEntryData(
                entryDate: $input->disposalDate,
                lines: $lines,
                entryType: JournalEntryType::ADJUSTING,
                reference: $asset->asset_code,
                narration: $narration,
                currency: $asset->currency,
                creatorId: $actor->id,
                sourceType: FixedAsset::class,
                sourceId: $asset->id,
            ));

            $posted = $this->postEntry->execute([
                'entry_id' => $draft->id,
                'actor_id' => $actor->id,
                'bypass_approval' => true,
            ]);

            $disposalEntryId = $posted->id;
        }

        $asset->update([
            'status' => FixedAssetStatus::DISPOSED,
            'disposed_at' => $input->disposalDate,
            'disposal_proceeds' => $input->proceedsMinor,
            'disposal_journal_entry_id' => $disposalEntryId,
            'net_book_value' => 0,
        ]);

        $fresh = $asset->fresh(['schedules', 'disposalJournalEntry']);

        $this->audit->record(
            action: AuditAction::ASSET_DISPOSED,
            auditable: $fresh,
            before: $before,
            after: $fresh->toArray(),
            actorId: $actor->id,
            reason: $input->reason ?? 'Fixed asset disposed',
            originContext: self::class,
        );

        return $fresh;
    }

    private function resolveSystemAccountId(string $configKey, string $defaultCode): int
    {
        $code = (string) Config::get("ium.accounting.accounts.{$configKey}", $defaultCode);
        $account = Account::where('code', $code)->first()
            ?? throw AccountNotFoundException::forCode($code);

        return $account->id;
    }
}
