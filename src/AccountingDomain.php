<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting;

use Obelaw\Ium\Abstracts\Domain;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\AclIngestionService;
use Obelaw\Ium\Accounting\Services\AuditService;
use Obelaw\Ium\Accounting\Services\ClosingService;
use Obelaw\Ium\Accounting\Services\CostCenterService;
use Obelaw\Ium\Accounting\Services\CurrencyService;
use Obelaw\Ium\Accounting\Services\FiscalPeriodService;
use Obelaw\Ium\Accounting\Services\FixedAssetService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;
use Obelaw\Ium\Accounting\Services\LedgerService;
use Obelaw\Ium\Engine\ObelawConfigManager;

/**
 * Accounting Domain Gateway for Obelawium.
 *
 * Implements the fluent chain: ium()->accounting()->{service}()->method()
 */
final class AccountingDomain extends Domain
{
    public function __construct(
        ObelawConfigManager $config,
        private readonly ?AccountingManager $manager = null
    ) {
        parent::__construct($config);
    }

    private function getManager(): AccountingManager
    {
        return $this->manager ?? app(AccountingManager::class);
    }

    /**
     * Chart of Accounts (COA) management service.
     */
    public function accounts(): AccountService
    {
        return $this->getManager()->accounts();
    }

    /**
     * Journal voucher drafting, validation, posting, and reversal service.
     */
    public function journalEntries(): JournalEntryService
    {
        return $this->getManager()->journalEntries();
    }

    /**
     * General Ledger reporting (Trial Balance, Balance Sheet, Income Statement).
     */
    public function ledger(): LedgerService
    {
        return $this->getManager()->ledger();
    }

    /**
     * Fiscal years and monthly/quarterly period governance service.
     */
    public function fiscalPeriods(): FiscalPeriodService
    {
        return $this->getManager()->fiscalPeriods();
    }

    /**
     * Analytical accounting dimensions and cost centers service.
     */
    public function costCenters(): CostCenterService
    {
        return $this->getManager()->costCenters();
    }

    /**
     * Fiscal year-end nominal closing and retained earnings rollover service.
     */
    public function closing(): ClosingService
    {
        return $this->getManager()->closing();
    }

    /**
     * Immutable forensic accounting audit trail service.
     */
    public function audit(): AuditService
    {
        return $this->getManager()->audit();
    }

    /**
     * Anti-Corruption Layer (ACL) for operational domain event ingestion.
     */
    public function acl(): AclIngestionService
    {
        return $this->getManager()->acl();
    }

    /**
     * Multi-Currency and foreign exchange conversion engine service.
     */
    public function currencies(): CurrencyService
    {
        return $this->getManager()->currencies();
    }

    /**
     * Fixed Assets management, subledger valuation, and automated depreciation engine.
     */
    public function fixedAssets(): FixedAssetService
    {
        return $this->getManager()->fixedAssets();
    }
}
