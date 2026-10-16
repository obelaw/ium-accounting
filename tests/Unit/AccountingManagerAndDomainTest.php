<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\AccountingDomain;
use Obelaw\Ium\Accounting\AccountingManager;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\AclIngestionService;
use Obelaw\Ium\Accounting\Services\AuditService;
use Obelaw\Ium\Accounting\Services\ClosingService;
use Obelaw\Ium\Accounting\Services\CostCenterService;
use Obelaw\Ium\Accounting\Services\CurrencyService;
use Obelaw\Ium\Accounting\Services\FiscalPeriodService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;
use Obelaw\Ium\Accounting\Services\LedgerService;
use Obelaw\Ium\Engine\ObelawConfigManager;

test('AccountingManager resolves all 9 domain services from the container', function () {
    $manager = new AccountingManager();

    expect($manager->accounts())->toBeInstanceOf(AccountService::class)
        ->and($manager->journalEntries())->toBeInstanceOf(JournalEntryService::class)
        ->and($manager->ledger())->toBeInstanceOf(LedgerService::class)
        ->and($manager->fiscalPeriods())->toBeInstanceOf(FiscalPeriodService::class)
        ->and($manager->costCenters())->toBeInstanceOf(CostCenterService::class)
        ->and($manager->closing())->toBeInstanceOf(ClosingService::class)
        ->and($manager->audit())->toBeInstanceOf(AuditService::class)
        ->and($manager->acl())->toBeInstanceOf(AclIngestionService::class)
        ->and($manager->currencies())->toBeInstanceOf(CurrencyService::class);
});

test('AccountingDomain delegates all 9 service accessors to AccountingManager', function () {
    $config = ObelawConfigManager::getInstance();
    $domainWithDefaultManager = new AccountingDomain($config);
    $domainWithInjectedManager = new AccountingDomain($config, new AccountingManager());

    foreach ([$domainWithDefaultManager, $domainWithInjectedManager] as $domain) {
        expect($domain->accounts())->toBeInstanceOf(AccountService::class)
            ->and($domain->journalEntries())->toBeInstanceOf(JournalEntryService::class)
            ->and($domain->ledger())->toBeInstanceOf(LedgerService::class)
            ->and($domain->fiscalPeriods())->toBeInstanceOf(FiscalPeriodService::class)
            ->and($domain->costCenters())->toBeInstanceOf(CostCenterService::class)
            ->and($domain->closing())->toBeInstanceOf(ClosingService::class)
            ->and($domain->audit())->toBeInstanceOf(AuditService::class)
            ->and($domain->acl())->toBeInstanceOf(AclIngestionService::class)
            ->and($domain->currencies())->toBeInstanceOf(CurrencyService::class);
    }
});
