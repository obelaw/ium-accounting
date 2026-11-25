<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class AgingReport
{
    /**
     * @param list<AgingBucketRow> $rows
     */
    public function __construct(
        public string $asOfDate,
        public string $currency,
        public int $accountId,
        public string $accountCode,
        public array $rows,
        public int $totalCurrent,
        public int $totalDays1To30,
        public int $totalDays31To60,
        public int $totalDays61To90,
        public int $totalDaysOver90,
        public int $grandTotal,
    ) {}
}
