<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

final readonly class SubmitJournalEntryDto
{
    public function __construct(
        public int $entryId,
        public ?string $actorId = null,
        public ?string $notes = null,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            entryId: (int) ($data['entry_id'] ?? $data['id'] ?? 0),
            actorId: isset($data['actor_id']) ? (string) $data['actor_id'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }
}
