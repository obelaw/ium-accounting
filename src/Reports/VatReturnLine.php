<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class VatReturnLine
{
    public function __construct(
        public int $taxId,
        public string $taxCode,
        public string $taxName,
        public string $scope,
        public int $rateBps,
        public int $outputTax,
        public int $inputTax,
        public int $netTax,
    ) {}
}
