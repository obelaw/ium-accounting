<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting;

use Obelaw\Ium\Abstracts\Domain;
use Obelaw\Ium\Accounting\Gateways\AccountGateway;
use Obelaw\Ium\Accounting\Gateways\AclGateway;
use Obelaw\Ium\Accounting\Gateways\AuditGateway;
use Obelaw\Ium\Accounting\Gateways\ClosingGateway;
use Obelaw\Ium\Accounting\Gateways\CostCenterGateway;
use Obelaw\Ium\Accounting\Gateways\CurrencyGateway;
use Obelaw\Ium\Accounting\Gateways\FiscalPeriodGateway;
use Obelaw\Ium\Accounting\Gateways\FixedAssetGateway;
use Obelaw\Ium\Accounting\Gateways\FxGateway;
use Obelaw\Ium\Accounting\Gateways\JournalEntryGateway;
use Obelaw\Ium\Accounting\Gateways\JournalGateway;
use Obelaw\Ium\Accounting\Gateways\ReconciliationGateway;
use Obelaw\Ium\Accounting\Gateways\ReportingGateway;
use Obelaw\Ium\Accounting\Gateways\TaxGateway;

/**
 * Enterprise Accounting Domain Gateway for Obelawium.
 *
 * Fluent DSL: `ium()->accounting()->{gateway}()->{operation}()`
 */
final class AccountingDomain extends Domain
{
    public function currencies(): CurrencyGateway
    {
        return app(CurrencyGateway::class);
    }

    public function accounts(): AccountGateway
    {
        return app(AccountGateway::class);
    }

    public function journals(): JournalGateway
    {
        return app(JournalGateway::class);
    }

    public function costCenters(): CostCenterGateway
    {
        return app(CostCenterGateway::class);
    }

    public function fiscalPeriods(): FiscalPeriodGateway
    {
        return app(FiscalPeriodGateway::class);
    }

    public function taxes(): TaxGateway
    {
        return app(TaxGateway::class);
    }

    public function journalEntries(): JournalEntryGateway
    {
        return app(JournalEntryGateway::class);
    }

    public function reconciliations(): ReconciliationGateway
    {
        return app(ReconciliationGateway::class);
    }

    public function fx(): FxGateway
    {
        return app(FxGateway::class);
    }

    public function fixedAssets(): FixedAssetGateway
    {
        return app(FixedAssetGateway::class);
    }

    public function closing(): ClosingGateway
    {
        return app(ClosingGateway::class);
    }

    public function reports(): ReportingGateway
    {
        return app(ReportingGateway::class);
    }

    public function acl(): AclGateway
    {
        return app(AclGateway::class);
    }

    public function audit(): AuditGateway
    {
        return app(AuditGateway::class);
    }
}
