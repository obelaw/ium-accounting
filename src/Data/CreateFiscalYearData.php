<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use Carbon\Carbon;
use InvalidArgumentException;

final readonly class CreateFiscalYearData
{
    public string $startDate;

    public string $endDate;

    public function __construct(
        public string $name,
        string $startDate,
        string $endDate,
        public bool $generateMonthlyPeriods = false,
    ) {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($end->lt($start)) {
            throw new InvalidArgumentException('Fiscal year end date must be on or after start date.');
        }

        $this->startDate = $start->format('Y-m-d');
        $this->endDate = $end->format('Y-m-d');
    }
}
