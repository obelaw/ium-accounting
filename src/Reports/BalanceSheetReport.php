<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class BalanceSheetReport
{
    /**
     * @param list<FinancialStatementLine> $assets
     * @param list<FinancialStatementLine> $liabilities
     * @param list<FinancialStatementLine> $equity
     */
    public function __construct(
        public string $currency,
        public int $precision,
        public ?string $asOfDate,
        public array $assets,
        public array $liabilities,
        public array $equity,
        public int $totalAssets,
        public int $totalLiabilities,
        public int $totalEquity,
        public int $currentEarnings,
        public int $totalEquityWithEarnings,
        public int $totalLiabilitiesAndEquity,
        public bool $isBalanced,
        public int $discrepancy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'precision' => $this->precision,
            'as_of_date' => $this->asOfDate,
            'assets' => array_map(static fn (FinancialStatementLine $l) => $l->toArray(), $this->assets),
            'liabilities' => array_map(static fn (FinancialStatementLine $l) => $l->toArray(), $this->liabilities),
            'equity' => array_map(static fn (FinancialStatementLine $l) => $l->toArray(), $this->equity),
            'total_assets' => $this->totalAssets,
            'total_liabilities' => $this->totalLiabilities,
            'total_equity' => $this->totalEquity,
            'current_earnings' => $this->currentEarnings,
            'total_equity_with_earnings' => $this->totalEquityWithEarnings,
            'total_liabilities_and_equity' => $this->totalLiabilitiesAndEquity,
            'is_balanced' => $this->isBalanced,
            'discrepancy' => $this->discrepancy,
        ];
    }
}
