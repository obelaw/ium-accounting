<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use Carbon\Carbon;
use InvalidArgumentException;

final readonly class CreateFiscalPeriodData
{
    public string $startDate;

    public string $endDate;

    public function __construct(
        public int $fiscalYearId,
        public int $periodNumber,
        public string $name,
        string $startDate,
        string $endDate,
    ) {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        if ($periodNumber <= 0) {
            throw new InvalidArgumentException('Period number must be positive.');
        }

        if ($end->lt($start)) {
            throw new InvalidArgumentException('Fiscal period end date must be on or after start date.');
        }

        $this->startDate = $start->format('Y-m-d');
        $this->endDate = $end->format('Y-m-d');
    }
}
