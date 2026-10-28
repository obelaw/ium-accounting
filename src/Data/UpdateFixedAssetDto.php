<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

final readonly class UpdateFixedAssetDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $serialNumber = null,
        public ?string $description = null,
        public ?int $costCenterId = null,
        public ?int $salvageValue = null,
        public ?int $usefulLifeMonths = null,
        public ?string $actorId = null,
        public ?array $metadata = null,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            name: isset($data['name']) ? trim((string) $data['name']) : null,
            serialNumber: isset($data['serial_number']) ? trim((string) $data['serial_number']) : null,
            description: isset($data['description']) ? trim((string) $data['description']) : null,
            costCenterId: isset($data['cost_center_id']) ? (int) $data['cost_center_id'] : null,
            salvageValue: isset($data['salvage_value']) ? (int) $data['salvage_value'] : null,
            usefulLifeMonths: isset($data['useful_life_months']) ? (int) $data['useful_life_months'] : null,
            actorId: isset($data['actor_id']) ? (string) $data['actor_id'] : null,
            metadata: isset($data['metadata']) ? (array) $data['metadata'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'serial_number' => $this->serialNumber,
            'description' => $this->description,
            'cost_center_id' => $this->costCenterId,
            'salvage_value' => $this->salvageValue,
            'useful_life_months' => $this->usefulLifeMonths,
            'metadata' => $this->metadata,
        ], static fn($value) => $value !== null);
    }
}
