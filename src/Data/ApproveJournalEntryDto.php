<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;

final readonly class ApproveJournalEntryDto
{
    public function __construct(
        public int $entryId,
        public string $approverId,
        public ?string $notes = null,
    ) {
        if (trim($approverId) === '') {
            throw new InvalidArgumentException('approver_id is required.');
        }
    }

    public static function from(array $data): self
    {
        return new self(
            entryId: (int) ($data['entry_id'] ?? $data['id'] ?? 0),
            approverId: trim((string) ($data['approver_id'] ?? throw new InvalidArgumentException('approver_id is required.'))),
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }
}
