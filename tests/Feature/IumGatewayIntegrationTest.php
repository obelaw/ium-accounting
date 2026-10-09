<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\AccountingDomain;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\AclIngestionService;
use Obelaw\Ium\Accounting\Services\AuditService;
use Obelaw\Ium\Accounting\Services\ClosingService;
use Obelaw\Ium\Accounting\Services\CostCenterService;
use Obelaw\Ium\Accounting\Services\FiscalPeriodService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;
use Obelaw\Ium\Accounting\Services\LedgerService;

test('accounting domain is registered with the central Obelawium gateway', function () {
    expect(ium()->hasDomain('accounting'))->toBeTrue()
        ->and(ium()->accounting())->toBeInstanceOf(AccountingDomain::class);
});

test('fluent DSL resolves all accounting domain services', function () {
    $accounting = ium()->accounting();

    expect($accounting->accounts())->toBeInstanceOf(AccountService::class)
        ->and($accounting->journalEntries())->toBeInstanceOf(JournalEntryService::class)
        ->and($accounting->ledger())->toBeInstanceOf(LedgerService::class)
        ->and($accounting->fiscalPeriods())->toBeInstanceOf(FiscalPeriodService::class)
        ->and($accounting->costCenters())->toBeInstanceOf(CostCenterService::class)
        ->and($accounting->closing())->toBeInstanceOf(ClosingService::class)
        ->and($accounting->audit())->toBeInstanceOf(AuditService::class)
        ->and($accounting->acl())->toBeInstanceOf(AclIngestionService::class);
});
