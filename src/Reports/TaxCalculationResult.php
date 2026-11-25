<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class TaxCalculationResult
{
    /**
     * @param list<TaxBreakdownLine> $lines
     */
    public function __construct(
        public int $netAmount,
        public int $totalTax,
        public int $totalWithholding,
        public int $grossAmount,
        public array $lines,
    ) {}
}
