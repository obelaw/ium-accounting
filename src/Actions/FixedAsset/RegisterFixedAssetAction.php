<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\FixedAsset;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateFixedAssetData;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\FixedAssetStatus;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\NonPostingAccountException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\FixedAsset;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<CreateFixedAssetData, FixedAsset>
 */
final class RegisterFixedAssetAction extends Action
{
    protected ?string $ability = 'accounting.fixed_asset.create';

    public function __construct(
        private readonly GenerateDepreciationScheduleAction $generateSchedule,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): FixedAsset
    {
        $this->validateAccount($input->assetAccountId, AccountType::ASSET, 'asset');
        $this->validateAccount($input->accumulatedDepreciationAccountId, AccountType::ASSET, 'accumulated_depreciation');
        $this->validateAccount($input->depreciationExpenseAccountId, AccountType::EXPENSE, 'depreciation_expense');

        $asset = FixedAsset::create([
            'asset_code' => $input->assetCode,
            'name' => $input->name,
            'serial_number' => $input->serialNumber,
            'description' => $input->description,
            'status' => $input->activateImmediately ? FixedAssetStatus::ACTIVE : FixedAssetStatus::DRAFT,
            'depreciation_method' => $input->depreciationMethod,
            'declining_rate_bps' => $input->decliningRateBps,
            'purchase_date' => $input->purchaseDate,
            'in_service_date' => $input->inServiceDate,
            'currency' => $input->currency,
            'acquisition_cost' => $input->acquisitionCost,
            'salvage_value' => $input->salvageValue,
            'accumulated_depreciation' => 0,
            'net_book_value' => $input->acquisitionCost,
            'useful_life_months' => $input->usefulLifeMonths,
            'asset_account_id' => $input->assetAccountId,
            'accumulated_depreciation_account_id' => $input->accumulatedDepreciationAccountId,
            'depreciation_expense_account_id' => $input->depreciationExpenseAccountId,
            'disposal_gain_account_id' => $input->disposalGainAccountId,
            'disposal_loss_account_id' => $input->disposalLossAccountId,
            'cost_center_id' => $input->costCenterId,
            'creator_id' => $actor->id,
            'metadata' => $input->metadata,
        ]);

        if ($input->activateImmediately) {
            $this->generateSchedule->execute($asset->id);
        }

        $fresh = $asset->fresh(['schedules']);

        $this->audit->record(
            action: AuditAction::CREATED,
            auditable: $fresh,
            after: $fresh->toArray(),
            actorId: $actor->id,
            originContext: self::class,
        );

        return $fresh;
    }

    private function validateAccount(int $accountId, AccountType $expectedType, string $role): void
    {
        $account = Account::find($accountId) ?? throw AccountNotFoundException::forId($accountId);

        if (! $account->allowsPostings()) {
            throw NonPostingAccountException::forAccount($account->code, $account->name, $account->classification->value);
        }

        if ($account->type !== $expectedType) {
            throw new InvalidArgumentException(
                "Fixed asset [{$role}] account ({$account->code}) must be of type [{$expectedType->value}], got [{$account->type->value}]."
            );
        }
    }
}
