<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class AccountLedgerReport
{
    /**
     * @param list<AccountLedgerEntry> $entries
     */
    public function __construct(
        public int $accountId,
        public string $accountCode,
        public string $accountName,
        public string $normalBalance,
        public string $currency,
        public ?string $fromDate,
        public ?string $toDate,
        public int $openingBalance,
        public int $periodDebit,
        public int $periodCredit,
        public int $closingBalance,
        public array $entries,
    ) {}
}
