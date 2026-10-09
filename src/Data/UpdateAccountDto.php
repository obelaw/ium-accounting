<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;

final readonly class UpdateAccountDto
{
    public function __construct(
        public ?string $name = null,
        public ?AccountType $type = null,
        public ?AccountClassification $classification = null,
        public ?NormalBalance $normalBalance = null,
        public ?int $parentId = null,
        public ?bool $isLeaf = null,
        public ?bool $isReconciliation = null,
        public ?bool $isActive = null,
        public ?string $actorId = null,
        public ?array $metadata = null,
    ) {}

    public static function from(array $data): self
    {
        $type = isset($data['type'])
            ? ($data['type'] instanceof AccountType ? $data['type'] : AccountType::from((string) $data['type']))
            : null;

        $classification = isset($data['classification'])
            ? ($data['classification'] instanceof AccountClassification ? $data['classification'] : AccountClassification::from((string) $data['classification']))
            : null;

        $normalBalance = isset($data['normal_balance'])
            ? ($data['normal_balance'] instanceof NormalBalance ? $data['normal_balance'] : NormalBalance::from((string) $data['normal_balance']))
            : null;

        return new self(
            name: isset($data['name']) ? trim((string) $data['name']) : null,
            type: $type,
            classification: $classification,
            normalBalance: $normalBalance,
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            isLeaf: isset($data['is_leaf']) ? (bool) $data['is_leaf'] : null,
            isReconciliation: isset($data['is_reconciliation']) ? (bool) $data['is_reconciliation'] : null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
            actorId: isset($data['actor_id']) ? (string) $data['actor_id'] : null,
            metadata: isset($data['metadata']) ? (array) $data['metadata'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type?->value,
            'classification' => $this->classification?->value,
            'normal_balance' => $this->normalBalance?->value,
            'parent_id' => $this->parentId,
            'is_leaf' => $this->isLeaf,
            'is_reconciliation' => $this->isReconciliation,
            'is_active' => $this->isActive,
            'actor_id' => $this->actorId,
            'metadata' => $this->metadata,
        ], static fn($v) => $v !== null);
    }
}
