<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class AccountLedgerEntry
{
    public function __construct(
        public int $postingId,
        public int $journalEntryId,
        public string $entryNumber,
        public string $postingDate,
        public ?string $narration,
        public int $debit,
        public int $credit,
        public int $runningBalance,
        public string $currency,
        public int $amountCurrency,
    ) {}
}
