<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Obelaw\Ium\Accounting\Models\JournalEntry;

final readonly class JournalEntrySubmitted
{
    public function __construct(public JournalEntry $entry, public ?string $actorId = null) {}
}
