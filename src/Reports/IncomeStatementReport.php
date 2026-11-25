<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class IncomeStatementReport
{
    /**
     * @param list<FinancialStatementLine> $revenues
     * @param list<FinancialStatementLine> $expenses
     */
    public function __construct(
        public string $currency,
        public int $precision,
        public ?string $fromDate,
        public ?string $toDate,
        public ?int $fiscalPeriodId,
        public ?int $costCenterId,
        public array $revenues,
        public array $expenses,
        public int $totalRevenue,
        public int $totalExpense,
        public int $netIncome,
        public bool $isProfitable,
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
            'fiscal_period_id' => $this->fiscalPeriodId,
            'cost_center_id' => $this->costCenterId,
            'revenues' => array_map(static fn (FinancialStatementLine $l) => $l->toArray(), $this->revenues),
            'expenses' => array_map(static fn (FinancialStatementLine $l) => $l->toArray(), $this->expenses),
            'total_revenue' => $this->totalRevenue,
            'total_expense' => $this->totalExpense,
            'net_income' => $this->netIncome,
            'is_profitable' => $this->isProfitable,
        ];
    }
}
