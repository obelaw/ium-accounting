<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;

final readonly class ReverseJournalEntryDto
{
    public function __construct(
        public int $entryId,
        public string $actorId,
        public string $reason,
        public ?string $reversalDate = null,
        public ?int $fiscalPeriodId = null,
    ) {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Reversal reason is mandatory.');
        }
    }

    public static function from(array $data): self
    {
        return new self(
            entryId: (int) ($data['entry_id'] ?? $data['id'] ?? 0),
            actorId: trim((string) ($data['actor_id'] ?? throw new InvalidArgumentException('actor_id is required.'))),
            reason: trim((string) ($data['reason'] ?? throw new InvalidArgumentException('Reversal reason is mandatory.'))),
            reversalDate: isset($data['reversal_date']) ? (string) $data['reversal_date'] : null,
            fiscalPeriodId: isset($data['fiscal_period_id']) ? (int) $data['fiscal_period_id'] : null,
        );
    }
}
