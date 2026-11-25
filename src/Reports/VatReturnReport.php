<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class VatReturnReport
{
    /**
     * @param list<VatReturnLine> $lines
     */
    public function __construct(
        public string $currency,
        public string $fromDate,
        public string $toDate,
        public array $lines,
        public int $totalOutputVat,
        public int $totalInputVat,
        public int $netPayable,
    ) {}
}
