<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class PartnerLedgerEntry
{
    public function __construct(
        public int $lineId,
        public int $journalEntryId,
        public string $entryNumber,
        public string $postingDate,
        public ?string $dueDate,
        public string $accountCode,
        public ?string $narration,
        public int $debit,
        public int $credit,
        public int $runningBalance,
        public int $openResidual,
        public string $reconciliationStatus,
    ) {}
}
