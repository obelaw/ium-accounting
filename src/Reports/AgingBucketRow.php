<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class AgingBucketRow
{
    public function __construct(
        public string $partyType,
        public int $partyId,
        public int $current,
        public int $days1To30,
        public int $days31To60,
        public int $days61To90,
        public int $daysOver90,
        public int $totalOpen,
    ) {}
}
