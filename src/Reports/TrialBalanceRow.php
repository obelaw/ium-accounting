<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class TrialBalanceRow
{
    public function __construct(
        public int $accountId,
        public string $accountCode,
        public string $accountName,
        public string $type,
        public string $normalBalance,
        public int $openingDebit,
        public int $openingCredit,
        public int $periodDebit,
        public int $periodCredit,
        public int $endingDebit,
        public int $endingCredit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'account_code' => $this->accountCode,
            'account_name' => $this->accountName,
            'type' => $this->type,
            'normal_balance' => $this->normalBalance,
            'opening_debit' => $this->openingDebit,
            'opening_credit' => $this->openingCredit,
            'period_debit' => $this->periodDebit,
            'period_credit' => $this->periodCredit,
            'ending_debit' => $this->endingDebit,
            'ending_credit' => $this->endingCredit,
        ];
    }
}
