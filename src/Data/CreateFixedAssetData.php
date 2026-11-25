<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use Carbon\Carbon;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\DepreciationMethod;

final readonly class CreateFixedAssetData
{
    public string $purchaseDate;

    public string $inServiceDate;

    public string $currency;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $assetCode,
        public string $name,
        string $purchaseDate,
        string $inServiceDate,
        public int $acquisitionCost,
        public int $usefulLifeMonths,
        public int $assetAccountId,
        public int $accumulatedDepreciationAccountId,
        public int $depreciationExpenseAccountId,
        public int $salvageValue = 0,
        public DepreciationMethod $depreciationMethod = DepreciationMethod::STRAIGHT_LINE,
        public ?int $decliningRateBps = null,
        public ?int $disposalGainAccountId = null,
        public ?int $disposalLossAccountId = null,
        public ?int $costCenterId = null,
        public ?string $serialNumber = null,
        public ?string $description = null,
        string $currency = 'USD',
        public bool $activateImmediately = true,
        public array $metadata = [],
    ) {
        if ($acquisitionCost <= 0) {
            throw new InvalidArgumentException('Acquisition cost must be positive.');
        }

        if ($salvageValue < 0 || $salvageValue >= $acquisitionCost) {
            throw new InvalidArgumentException('Salvage value must be >= 0 and strictly less than acquisition cost.');
        }

        if ($usefulLifeMonths <= 0) {
            throw new InvalidArgumentException('Useful life in months must be greater than zero.');
        }

        $this->purchaseDate = Carbon::parse($purchaseDate)->format('Y-m-d');
        $this->inServiceDate = Carbon::parse($inServiceDate)->format('Y-m-d');
        $this->currency = strtoupper(trim($currency));
    }
}
