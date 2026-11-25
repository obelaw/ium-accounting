<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Obelaw\Ium\Accounting\Models\FiscalYear;
use Obelaw\Ium\Accounting\Models\JournalEntry;

final readonly class FiscalYearClosed
{
    public function __construct(public FiscalYear $fiscalYear, public ?JournalEntry $closingEntry, public string $actorId) {}
}
