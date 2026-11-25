<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\JournalType;

final readonly class CreateJournalData
{
    public string $code;

    public string $sequencePrefix;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $code,
        public string $name,
        public JournalType $type = JournalType::GENERAL,
        ?string $sequencePrefix = null,
        public ?int $defaultDebitAccountId = null,
        public ?int $defaultCreditAccountId = null,
        public ?string $currency = null,
        public bool $isActive = true,
        public array $metadata = [],
    ) {
        $cleanCode = strtoupper(trim($code));
        if ($cleanCode === '') {
            throw new InvalidArgumentException('Journal code cannot be empty.');
        }

        $this->code = $cleanCode;
        $this->sequencePrefix = strtoupper(trim($sequencePrefix ?? $type->defaultPrefix()));
    }
}
