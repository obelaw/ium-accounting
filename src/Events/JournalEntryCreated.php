<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Obelaw\Ium\Accounting\Models\JournalEntry;

final class JournalEntryCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly JournalEntry $entry,
        public readonly ?string $actorId = null
    ) {}
}
