<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;

final readonly class CreateAccountDto
{
    public function __construct(
        public string $code,
        public string $name,
        public AccountType $type,
        public AccountClassification $classification,
        public NormalBalance $normalBalance,
        public ?int $parentId = null,
        public string $currency = 'USD',
        public bool $isLeaf = true,
        public bool $isReconciliation = false,
        public bool $isActive = true,
        public ?string $actorId = null,
        public array $metadata = [],
    ) {}

    public static function from(array $data): self
    {
        $code = trim((string) ($data['code'] ?? throw new InvalidArgumentException('Account code is required.')));
        $name = trim((string) ($data['name'] ?? throw new InvalidArgumentException('Account name is required.')));

        $rawType = $data['type'] ?? throw new InvalidArgumentException('Account type is required.');
        $type = $rawType instanceof AccountType ? $rawType : AccountType::from((string) $rawType);

        $rawClassification = $data['classification'] ?? AccountClassification::POSTING;
        $classification = $rawClassification instanceof AccountClassification
            ? $rawClassification
            : AccountClassification::from((string) $rawClassification);

        $rawNormalBalance = $data['normal_balance'] ?? $type->normalBalance();
        $normalBalance = $rawNormalBalance instanceof NormalBalance
            ? $rawNormalBalance
            : NormalBalance::from((string) $rawNormalBalance);

        $isLeaf = isset($data['is_leaf']) ? (bool) $data['is_leaf'] : ($classification === AccountClassification::POSTING);

        return new self(
            code: $code,
            name: $name,
            type: $type,
            classification: $classification,
            normalBalance: $normalBalance,
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            isLeaf: $isLeaf,
            isReconciliation: (bool) ($data['is_reconciliation'] ?? false),
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
            'type' => $this->type->value,
            'classification' => $this->classification->value,
            'normal_balance' => $this->normalBalance->value,
            'parent_id' => $this->parentId,
            'currency' => $this->currency,
            'is_leaf' => $this->isLeaf,
            'is_reconciliation' => $this->isReconciliation,
            'is_active' => $this->isActive,
            'actor_id' => $this->actorId,
            'metadata' => $this->metadata,
        ];
    }
}
