<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Providers;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Obelaw\Ium\Accounting\AccountingDomain;
use Obelaw\Ium\Accounting\AccountingManager;
use Obelaw\Ium\Accounting\Console\Commands\MigrateAccountingCommand;
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
use Obelaw\Ium\Engine\ObelawConfigManager;
use Obelaw\Ium\Facades\Ium;

final class ObelawAccountingServiceProvider extends ServiceProvider
{
    /**
     * Runtime override for automatic migration loading.
     */
    public static ?bool $autoMigrate = null;

    /**
     * Runtime override for custom migration path.
     */
    public static ?string $customMigrationPath = null;

    /**
     * Configure whether migrations should be automatically registered.
     */
    public static function autoMigrate(bool $autoMigrate = true): void
    {
        static::$autoMigrate = $autoMigrate;
    }

    /**
     * Disable automatic migration registration.
     */
    public static function ignoreMigrations(): void
    {
        static::$autoMigrate = false;
    }

    /**
     * Set a custom migrations directory path.
     */
    public static function setMigrationPath(?string $path): void
    {
        static::$customMigrationPath = $path;
    }

    /**
     * Get the default migrations path for the package.
     */
    public static function defaultMigrationPath(): string
    {
        return realpath(__DIR__ . '/../../database/migrations') ?: (__DIR__ . '/../../database/migrations');
    }

    /**
     * Resolve the active migration path.
     */
    public static function getMigrationPath(): string
    {
        if (static::$customMigrationPath !== null && static::$customMigrationPath !== '') {
            return static::$customMigrationPath;
        }

        $configPath = config('ium.accounting.migrations.path') ?? config('ium.accounting.migration_path');
        if (! empty($configPath) && is_string($configPath)) {
            return $configPath;
        }

        if (class_exists(ObelawConfigManager::class)) {
            $iumPath = ObelawConfigManager::getInstance()->get('accounting.migrations.path')
                ?? ObelawConfigManager::getInstance()->get('accounting.migration_path');
            if (! empty($iumPath) && is_string($iumPath)) {
                return $iumPath;
            }
        }

        return static::defaultMigrationPath();
    }

    /**
     * Determine if migrations should be loaded automatically.
     */
    public static function shouldAutoMigrate(): bool
    {
        if (static::$autoMigrate !== null) {
            return static::$autoMigrate;
        }

        $configVal = config('ium.accounting.migrations.auto_migrate') ?? config('ium.accounting.auto_migrate');
        if ($configVal !== null) {
            return (bool) $configVal;
        }

        if (class_exists(ObelawConfigManager::class)) {
            $iumAutoMigrate = ObelawConfigManager::getInstance()->get('accounting.migrations.auto_migrate');
            if ($iumAutoMigrate !== null) {
                return (bool) $iumAutoMigrate;
            }

            $globalConfig = ObelawConfigManager::getInstance()->get('obelawium.db.migrations');
            if ($globalConfig !== null) {
                return (bool) $globalConfig;
            }
        }

        return true;
    }

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
        if (static::shouldAutoMigrate()) {
            $this->loadMigrationsFrom(static::getMigrationPath());
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedAccountingDemoCommand::class,
                MigrateAccountingCommand::class,
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
