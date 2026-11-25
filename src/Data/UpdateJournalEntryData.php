<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use Carbon\Carbon;
use InvalidArgumentException;

final readonly class UpdateJournalEntryData
{
    public ?string $entryDate;

    public ?string $currency;

    /**
     * @param list<JournalLineData>|null $lines
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public int $entryId,
        ?string $entryDate = null,
        public ?array $lines = null,
        public ?int $journalId = null,
        public ?int $fiscalPeriodId = null,
        public ?string $reference = null,
        public ?string $narration = null,
        ?string $currency = null,
        public ?float $exchangeRate = null,
        public ?array $metadata = null,
    ) {
        if ($lines !== null && $lines === []) {
            throw new InvalidArgumentException('Updated lines array cannot be empty.');
        }

        if ($exchangeRate !== null && $exchangeRate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be strictly positive.');
        }

        $this->entryDate = $entryDate !== null ? Carbon::parse($entryDate)->format('Y-m-d') : null;
        $this->currency = $currency !== null ? strtoupper(trim($currency)) : null;
    }
}
