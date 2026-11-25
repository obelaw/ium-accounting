<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class TaxBreakdownLine
{
    public function __construct(
        public int $taxId,
        public string $taxCode,
        public string $taxName,
        public int $taxAccountId,
        public int $baseAmount,
        public int $taxAmount,
        public bool $isWithholding,
        public bool $isInclusive,
    ) {}
}
