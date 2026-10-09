<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;

final readonly class PostJournalEntryDto
{
    public function __construct(
        public int $entryId,
        public string $actorId,
    ) {
        if (trim($actorId) === '') {
            throw new InvalidArgumentException('actor_id is required for posting.');
        }
    }

    public static function from(array $data): self
    {
        return new self(
            entryId: (int) ($data['entry_id'] ?? $data['id'] ?? 0),
            actorId: trim((string) ($data['actor_id'] ?? throw new InvalidArgumentException('actor_id is required.'))),
        );
    }
}
