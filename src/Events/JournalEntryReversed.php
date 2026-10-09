<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Obelaw\Ium\Accounting\Models\JournalEntry;

/**
 * Emitted when a journal entry is reversed via a Storno reversal voucher.
 */
final class JournalEntryReversed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly JournalEntry $originalEntry,
        public readonly JournalEntry $reversalEntry,
        public readonly string $actorId,
        public readonly string $reason
    ) {}
}
