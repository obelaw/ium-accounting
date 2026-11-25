<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class TrialBalanceReport
{
    /**
     * @param list<TrialBalanceRow> $rows
     */
    public function __construct(
        public string $currency,
        public int $precision,
        public ?string $fromDate,
        public ?string $toDate,
        public array $rows,
        public int $totalOpeningDebit,
        public int $totalOpeningCredit,
        public int $totalPeriodDebit,
        public int $totalPeriodCredit,
        public int $totalEndingDebit,
        public int $totalEndingCredit,
        public bool $isBalanced,
        public int $imbalance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'precision' => $this->precision,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'rows' => array_map(static fn (TrialBalanceRow $r) => $r->toArray(), $this->rows),
            'total_opening_debit' => $this->totalOpeningDebit,
            'total_opening_credit' => $this->totalOpeningCredit,
            'total_period_debit' => $this->totalPeriodDebit,
            'total_period_credit' => $this->totalPeriodCredit,
            'total_ending_debit' => $this->totalEndingDebit,
            'total_ending_credit' => $this->totalEndingCredit,
            'is_balanced' => $this->isBalanced,
            'imbalance' => $this->imbalance,
        ];
    }
}
