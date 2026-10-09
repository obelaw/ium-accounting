<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\DimensionType;

final readonly class CreateCostCenterDto
{
    public function __construct(
        public string $code,
        public string $name,
        public DimensionType $dimensionType = DimensionType::COST_CENTER,
        public ?int $parentId = null,
        public bool $isActive = true,
        public ?string $actorId = null,
        public array $metadata = [],
    ) {}

    public static function from(array $data): self
    {
        $code = trim((string) ($data['code'] ?? throw new InvalidArgumentException('Cost center code is required.')));
        $name = trim((string) ($data['name'] ?? throw new InvalidArgumentException('Cost center name is required.')));

        $rawType = $data['dimension_type'] ?? DimensionType::COST_CENTER;
        $dimensionType = $rawType instanceof DimensionType ? $rawType : DimensionType::from((string) $rawType);

        return new self(
            code: $code,
            name: $name,
            dimensionType: $dimensionType,
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
            actorId: isset($data['actor_id']) ? (string) $data['actor_id'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'dimension_type' => $this->dimensionType->value,
            'parent_id' => $this->parentId,
            'is_active' => $this->isActive,
            'actor_id' => $this->actorId,
            'metadata' => $this->metadata,
        ];
    }
}
