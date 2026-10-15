<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting;

use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\AclIngestionService;
use Obelaw\Ium\Accounting\Services\AuditService;
use Obelaw\Ium\Accounting\Services\ClosingService;
use Obelaw\Ium\Accounting\Services\CostCenterService;
use Obelaw\Ium\Accounting\Services\CurrencyService;
use Obelaw\Ium\Accounting\Services\FiscalPeriodService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;
use Obelaw\Ium\Accounting\Services\LedgerService;

/**
 * Internal service resolver for the Accounting domain.
 */
final class AccountingManager
{
    public function accounts(): AccountService
    {
        return app(AccountService::class);
    }

    public function journalEntries(): JournalEntryService
    {
        return app(JournalEntryService::class);
    }

    public function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    public function fiscalPeriods(): FiscalPeriodService
    {
        return app(FiscalPeriodService::class);
    }

    public function costCenters(): CostCenterService
    {
        return app(CostCenterService::class);
    }

    public function closing(): ClosingService
    {
        return app(ClosingService::class);
    }

    public function audit(): AuditService
    {
        return app(AuditService::class);
    }

    public function acl(): AclIngestionService
    {
        return app(AclIngestionService::class);
    }

    public function currencies(): CurrencyService
    {
        return app(CurrencyService::class);
    }
}
