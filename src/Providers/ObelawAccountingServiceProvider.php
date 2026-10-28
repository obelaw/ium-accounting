<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Providers;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Obelaw\Ium\Accounting\AccountingDomain;
use Obelaw\Ium\Accounting\AccountingManager;
use Obelaw\Ium\Accounting\Console\Commands\SeedAccountingDemoCommand;
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
use Obelaw\Ium\Facades\Ium;

final class ObelawAccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/accounting.php', 'ium.accounting');

        $this->app->singleton(AccountingManager::class, static fn() => new AccountingManager());

        $this->bindServices();

        // Register domain with central Obelawium gateway
        Ium::registerDomain('accounting', AccountingDomain::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedAccountingDemoCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'ium-accounting-migrations');

            $this->publishes([
                __DIR__ . '/../../config/accounting.php' => config_path('ium/accounting.php'),
            ], 'ium-accounting-config');

            $this->publishes([
                __DIR__ . '/../../database/seeders' => database_path('seeders'),
            ], 'ium-accounting-seeders');
        }

        AboutCommand::add('Obelawium', static fn() => ['Obelawium Accounting' => '0.1.0']);
    }

    private function bindServices(): void
    {
        $services = [
            AccountService::class,
            CostCenterService::class,
            FiscalPeriodService::class,
            FixedAssetService::class,
            JournalEntryService::class,
            LedgerService::class,
            ClosingService::class,
            AuditService::class,
            AclIngestionService::class,
            CurrencyService::class,
        ];

        foreach ($services as $service) {
            $this->app->singleton($service);
        }
    }
}
