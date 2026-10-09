<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;

final readonly class RejectJournalEntryDto
{
    public function __construct(
        public int $entryId,
        public string $actorId,
        public string $reason,
    ) {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Rejection reason is mandatory.');
        }
    }

    public static function from(array $data): self
    {
        return new self(
            entryId: (int) ($data['entry_id'] ?? $data['id'] ?? 0),
            actorId: trim((string) ($data['actor_id'] ?? 'system')),
            reason: trim((string) ($data['reason'] ?? throw new InvalidArgumentException('Rejection reason is mandatory.'))),
        );
    }
}
