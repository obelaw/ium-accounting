<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Obelaw\Ium\Accounting\Queries\AccountLedgerQuery;
use Obelaw\Ium\Accounting\Queries\AgingReportQuery;
use Obelaw\Ium\Accounting\Queries\BalanceSheetQuery;
use Obelaw\Ium\Accounting\Queries\IncomeStatementQuery;
use Obelaw\Ium\Accounting\Queries\PartnerLedgerQuery;
use Obelaw\Ium\Accounting\Queries\TrialBalanceQuery;
use Obelaw\Ium\Accounting\Queries\VatReturnQuery;
use Obelaw\Ium\Accounting\Reports\AccountLedgerReport;
use Obelaw\Ium\Accounting\Reports\AgingReport;
use Obelaw\Ium\Accounting\Reports\BalanceSheetReport;
use Obelaw\Ium\Accounting\Reports\IncomeStatementReport;
use Obelaw\Ium\Accounting\Reports\PartnerLedgerReport;
use Obelaw\Ium\Accounting\Reports\TrialBalanceReport;
use Obelaw\Ium\Accounting\Reports\VatReturnReport;

final class ReportingGateway
{
    public function __construct(
        private readonly TrialBalanceQuery $trialBalanceQuery,
        private readonly BalanceSheetQuery $balanceSheetQuery,
        private readonly IncomeStatementQuery $incomeStatementQuery,
        private readonly AccountLedgerQuery $accountLedgerQuery,
        private readonly PartnerLedgerQuery $partnerLedgerQuery,
        private readonly AgingReportQuery $agingReportQuery,
        private readonly VatReturnQuery $vatReturnQuery,
    ) {}

    public function trialBalance(
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $fiscalPeriodId = null,
        bool $includeZeroRows = false,
    ): TrialBalanceReport {
        return $this->trialBalanceQuery->get($fromDate, $toDate, $fiscalPeriodId, $includeZeroRows);
    }

    public function balanceSheet(?string $asOfDate = null): BalanceSheetReport
    {
        return $this->balanceSheetQuery->get($asOfDate);
    }

    public function incomeStatement(
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $fiscalPeriodId = null,
        ?int $costCenterId = null,
    ): IncomeStatementReport {
        return $this->incomeStatementQuery->get($fromDate, $toDate, $fiscalPeriodId, $costCenterId);
    }

    public function accountLedger(
        int $accountId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $costCenterId = null,
    ): AccountLedgerReport {
        return $this->accountLedgerQuery->get($accountId, $fromDate, $toDate, $costCenterId);
    }

    public function partnerLedger(
        string $partyType,
        int $partyId,
        ?int $accountId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): PartnerLedgerReport {
        return $this->partnerLedgerQuery->get($partyType, $partyId, $accountId, $fromDate, $toDate);
    }

    public function aging(
        int $accountId,
        ?string $asOfDate = null,
        ?string $partyType = null,
    ): AgingReport {
        return $this->agingReportQuery->get($accountId, $asOfDate, $partyType);
    }

    public function vatReturn(string $fromDate, string $toDate): VatReturnReport
    {
        return $this->vatReturnQuery->get($fromDate, $toDate);
    }
}
