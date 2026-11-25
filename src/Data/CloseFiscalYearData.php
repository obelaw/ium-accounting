<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use Carbon\Carbon;

final readonly class CloseFiscalYearData
{
    public ?string $closingDate;

    public function __construct(
        public int $fiscalYearId,
        public ?string $retainedEarningsAccountCode = null,
        ?string $closingDate = null,
        public ?string $notes = null,
    ) {
        $this->closingDate = $closingDate !== null ? Carbon::parse($closingDate)->format('Y-m-d') : null;
    }
}
