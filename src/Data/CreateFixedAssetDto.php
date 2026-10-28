<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\DepreciationMethod;

final readonly class CreateFixedAssetDto
{
    public function __construct(
        public string $assetCode,
        public string $name,
        public string $purchaseDate,
        public string $inServiceDate,
        public int $acquisitionCost,
        public int $salvageValue,
        public int $usefulLifeMonths,
        public int $assetAccountId,
        public int $accumulatedDepreciationAccountId,
        public int $depreciationExpenseAccountId,
        public DepreciationMethod $depreciationMethod = DepreciationMethod::STRAIGHT_LINE,
        public string $currency = 'USD',
        public ?int $costCenterId = null,
        public ?string $serialNumber = null,
        public ?string $description = null,
        public ?string $creatorId = null,
        public bool $activateImmediately = true,
        public array $metadata = [],
    ) {
        if (trim($this->assetCode) === '' || trim($this->name) === '') {
            throw new InvalidArgumentException('Asset code and name are required.');
        }
        if ($this->acquisitionCost <= 0) {
            throw new InvalidArgumentException('Acquisition cost must be a positive integer in minor units.');
        }
        if ($this->salvageValue < 0 || $this->salvageValue >= $this->acquisitionCost) {
            throw new InvalidArgumentException('Salvage value must be >= 0 and strictly less than acquisition cost.');
        }
        if ($this->usefulLifeMonths <= 0) {
            throw new InvalidArgumentException('Useful life in months must be greater than zero.');
        }
    }

    public static function from(array $data): self
    {
        $method = $data['depreciation_method'] ?? DepreciationMethod::STRAIGHT_LINE;

        return new self(
            assetCode: trim((string) ($data['asset_code'] ?? throw new InvalidArgumentException('asset_code is required.'))),
            name: trim((string) ($data['name'] ?? throw new InvalidArgumentException('name is required.'))),
            purchaseDate: (string) ($data['purchase_date'] ?? throw new InvalidArgumentException('purchase_date is required.')),
            inServiceDate: (string) ($data['in_service_date'] ?? $data['purchase_date']),
            acquisitionCost: (int) ($data['acquisition_cost'] ?? throw new InvalidArgumentException('acquisition_cost is required.')),
            salvageValue: (int) ($data['salvage_value'] ?? 0),
            usefulLifeMonths: (int) ($data['useful_life_months'] ?? throw new InvalidArgumentException('useful_life_months is required.')),
            assetAccountId: (int) ($data['asset_account_id'] ?? throw new InvalidArgumentException('asset_account_id is required.')),
            accumulatedDepreciationAccountId: (int) ($data['accumulated_depreciation_account_id'] ?? throw new InvalidArgumentException('accumulated_depreciation_account_id is required.')),
            depreciationExpenseAccountId: (int) ($data['depreciation_expense_account_id'] ?? throw new InvalidArgumentException('depreciation_expense_account_id is required.')),
            depreciationMethod: $method instanceof DepreciationMethod ? $method : DepreciationMethod::from((string) $method),
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            costCenterId: isset($data['cost_center_id']) ? (int) $data['cost_center_id'] : null,
            serialNumber: isset($data['serial_number']) ? trim((string) $data['serial_number']) : null,
            description: isset($data['description']) ? trim((string) $data['description']) : null,
            creatorId: isset($data['creator_id']) ? (string) $data['creator_id'] : null,
            activateImmediately: (bool) ($data['activate_immediately'] ?? true),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    public static function fromMajor(array $data, int $multiplier = 100): self
    {
        if (isset($data['acquisition_cost'])) {
            $data['acquisition_cost'] = (int) round(((float) $data['acquisition_cost']) * $multiplier);
        }
        if (isset($data['salvage_value'])) {
            $data['salvage_value'] = (int) round(((float) $data['salvage_value']) * $multiplier);
        }

        return self::from($data);
    }

    public function toArray(): array
    {
        return [
            'asset_code' => $this->assetCode,
            'name' => $this->name,
            'purchase_date' => $this->purchaseDate,
            'in_service_date' => $this->inServiceDate,
            'acquisition_cost' => $this->acquisitionCost,
            'salvage_value' => $this->salvageValue,
            'useful_life_months' => $this->usefulLifeMonths,
            'asset_account_id' => $this->assetAccountId,
            'accumulated_depreciation_account_id' => $this->accumulatedDepreciationAccountId,
            'depreciation_expense_account_id' => $this->depreciationExpenseAccountId,
            'depreciation_method' => $this->depreciationMethod->value,
            'currency' => $this->currency,
            'cost_center_id' => $this->costCenterId,
            'serial_number' => $this->serialNumber,
            'description' => $this->description,
            'creator_id' => $this->creatorId,
            'activate_immediately' => $this->activateImmediately,
            'metadata' => $this->metadata,
        ];
    }
}
