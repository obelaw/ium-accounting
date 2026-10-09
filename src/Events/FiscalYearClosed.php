<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Obelaw\Ium\Accounting\Models\FiscalYear;
use Obelaw\Ium\Accounting\Models\JournalEntry;

final class FiscalYearClosed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FiscalYear $fiscalYear,
        public readonly JournalEntry $closingEntry,
        public readonly string $actorId
    ) {}
}
