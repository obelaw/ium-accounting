<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Obelaw\Ium\Accounting\Models\JournalEntry;

final readonly class JournalEntryReversed
{
    public function __construct(
        public JournalEntry $originalEntry,
        public JournalEntry $reversalEntry,
        public string $actorId,
        public string $reason,
    ) {}
}
