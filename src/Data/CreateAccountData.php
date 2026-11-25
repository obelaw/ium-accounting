<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;

final readonly class CreateAccountData
{
    public string $code;

    public NormalBalance $normalBalance;

    public bool $isLeaf;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $code,
        public string $name,
        public AccountType $type,
        public AccountClassification $classification = AccountClassification::POSTING,
        ?NormalBalance $normalBalance = null,
        public ?int $parentId = null,
        public ?string $currency = null,
        ?bool $isLeaf = null,
        public bool $isReconcilable = false,
        public bool $isActive = true,
        public array $metadata = [],
    ) {
        $cleanCode = trim($code);
        if ($cleanCode === '') {
            throw new InvalidArgumentException('Account code cannot be empty.');
        }

        $this->code = $cleanCode;
        $this->normalBalance = $normalBalance ?? $type->naturalNormalBalance();
        $this->isLeaf = $isLeaf ?? $classification->allowsPosting();
    }
}
