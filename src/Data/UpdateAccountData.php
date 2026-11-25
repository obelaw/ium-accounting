<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

final readonly class UpdateAccountData
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public int $accountId,
        public ?string $name = null,
        public ?bool $isReconcilable = null,
        public ?bool $isActive = null,
        public ?string $currency = null,
        public ?array $metadata = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toUpdateArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'is_reconcilable' => $this->isReconcilable,
            'is_active' => $this->isActive,
            'currency' => $this->currency !== null ? strtoupper(trim($this->currency)) : null,
            'metadata' => $this->metadata,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
