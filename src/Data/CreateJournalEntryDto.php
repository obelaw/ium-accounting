<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;

final readonly class CreateJournalEntryDto
{
    /**
     * @param JournalEntryLineDto[] $lines
     */
    public function __construct(
        public string $entryDate,
        public array $lines,
        public ?string $entryNumber = null,
        public ?int $fiscalPeriodId = null,
        public JournalEntryType $entryType = JournalEntryType::STANDARD,
        public ?string $narration = null,
        public string $currency = 'USD',
        public float $exchangeRate = 1.0,
        public ?string $creatorId = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public array $metadata = [],
    ) {
        if (empty($lines)) {
            throw new InvalidArgumentException('Journal entry must contain at least one line item.');
        }
    }

    public static function from(array $data): self
    {
        $entryDate = (string) ($data['entry_date'] ?? throw new InvalidArgumentException('entry_date is required.'));

        $rawLines = $data['lines'] ?? throw new InvalidArgumentException('lines are required.');
        if (!is_array($rawLines) || empty($rawLines)) {
            throw new InvalidArgumentException('lines must be a non-empty array.');
        }

        $lines = array_map(
            static fn($line) => $line instanceof JournalEntryLineDto ? $line : JournalEntryLineDto::from((array) $line),
            $rawLines
        );

        $rawType = $data['entry_type'] ?? JournalEntryType::STANDARD;
        $entryType = $rawType instanceof JournalEntryType ? $rawType : JournalEntryType::from((string) $rawType);

        return new self(
            entryDate: $entryDate,
            lines: $lines,
            entryNumber: isset($data['entry_number']) ? trim((string) $data['entry_number']) : null,
            fiscalPeriodId: isset($data['fiscal_period_id']) ? (int) $data['fiscal_period_id'] : null,
            entryType: $entryType,
            narration: isset($data['narration']) ? trim((string) $data['narration']) : null,
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            exchangeRate: (float) ($data['exchange_rate'] ?? 1.0),
            creatorId: isset($data['creator_id']) ? (string) $data['creator_id'] : null,
            sourceType: isset($data['source_type']) ? (string) $data['source_type'] : null,
            sourceId: isset($data['source_id']) ? (int) $data['source_id'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'entry_number' => $this->entryNumber,
            'entry_date' => $this->entryDate,
            'fiscal_period_id' => $this->fiscalPeriodId,
            'entry_type' => $this->entryType->value,
            'narration' => $this->narration,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchangeRate,
            'creator_id' => $this->creatorId,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'lines' => array_map(static fn(JournalEntryLineDto $l) => $l->toArray(), $this->lines),
            'metadata' => $this->metadata,
        ];
    }
}
