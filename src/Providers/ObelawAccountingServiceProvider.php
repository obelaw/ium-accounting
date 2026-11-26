<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Providers;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Obelaw\Ium\Accounting\AccountingDomain;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;
use Obelaw\Ium\Accounting\Support\JournalLineBuilder;
use Obelaw\Ium\Accounting\Support\TaxCalculator;
use Obelaw\Ium\Facades\Ium;
use Obelaw\Ium\Modules\Module;
use Obelaw\Ium\Modules\ModuleScope;

final class ObelawAccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/accounting.php', 'ium.accounting');

        $this->app->singleton(ExchangeRateResolver::class);
        $this->app->singleton(JournalLineBuilder::class);
        $this->app->singleton(TaxCalculator::class);
        $this->app->singleton(AuditRecorder::class);

        Ium::registerModule(Module::make(
            name: 'accounting',
            domain: AccountingDomain::class,
            scope: ModuleScope::Tenant,
            migrationPaths: __DIR__ . '/../../database/migrations',
            version: '1.0.0',
            description: 'Enterprise General Ledger & Financial Management (FMS) Domain Module',
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'ium-accounting-migrations');

            $this->publishes([
                __DIR__ . '/../../config/accounting.php' => config_path('ium/accounting.php'),
            ], 'ium-accounting-config');
        }

        AboutCommand::add('Obelawium', static fn () => ['Obelawium Accounting' => '1.0.0']);

        if (class_exists(\Obelaw\Ium\Mcp\Facades\IumMcp::class)) {
            \Obelaw\Ium\Mcp\Facades\IumMcp::registerToolset(\Obelaw\Ium\Accounting\Mcp\AccountingToolset::class);
        }
    }
}
