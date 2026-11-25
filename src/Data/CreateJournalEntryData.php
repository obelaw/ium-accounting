<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use Carbon\Carbon;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;

final readonly class CreateJournalEntryData
{
    public string $entryDate;

    public string $currency;

    /**
     * @param list<JournalLineData> $lines
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $entryDate,
        public array $lines,
        public ?int $journalId = null,
        public ?string $journalCode = null,
        public ?int $fiscalPeriodId = null,
        public ?string $entryNumber = null,
        public JournalEntryType $entryType = JournalEntryType::STANDARD,
        public ?string $reference = null,
        public ?string $narration = null,
        string $currency = 'USD',
        public ?float $exchangeRate = null,
        public ?string $creatorId = null,
        public ?string $idempotencyKey = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public array $metadata = [],
    ) {
        if ($lines === []) {
            throw new InvalidArgumentException('Journal entry requires at least one line item.');
        }

        if ($exchangeRate !== null && $exchangeRate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be strictly positive.');
        }

        $this->entryDate = Carbon::parse($entryDate)->format('Y-m-d');
        $this->currency = strtoupper(trim($currency));
    }
}
