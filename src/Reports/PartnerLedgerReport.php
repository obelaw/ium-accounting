<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class PartnerLedgerReport
{
    /**
     * @param list<PartnerLedgerEntry> $entries
     */
    public function __construct(
        public string $partyType,
        public int $partyId,
        public string $currency,
        public ?string $fromDate,
        public ?string $toDate,
        public int $openingBalance,
        public int $totalDebit,
        public int $totalCredit,
        public int $closingBalance,
        public int $totalOpenResidual,
        public array $entries,
    ) {}
}
