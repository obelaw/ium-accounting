<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Obelaw\Ium\Accounting\Providers\ObelawAccountingServiceProvider;
use Obelaw\Ium\Engine\ObelawConfigManager;

afterEach(function () {
    config([
        'ium.accounting.migrations.auto_migrate' => true,
        'ium.accounting.migrations.path' => null,
        'ium.accounting.auto_migrate' => null,
        'ium.accounting.migration_path' => null,
    ]);
    ObelawAccountingServiceProvider::$autoMigrate = null;
    ObelawAccountingServiceProvider::$customMigrationPath = null;
    ObelawConfigManager::getInstance()->reset();
});

it('defaults auto_migrate to true and migration path to package database/migrations', function () {
    expect(ObelawAccountingServiceProvider::shouldAutoMigrate())->toBeTrue();
    expect(ObelawAccountingServiceProvider::getMigrationPath())->toBe(ObelawAccountingServiceProvider::defaultMigrationPath());
    expect(ium()->accounting()->shouldAutoMigrate())->toBeTrue();
    expect(ium()->accounting()->getMigrationPath())->toBe(ObelawAccountingServiceProvider::defaultMigrationPath());
});

it('disables auto migration via ium.accounting.migrations.auto_migrate configuration', function () {
    config(['ium.accounting.migrations.auto_migrate' => false]);

    expect(ObelawAccountingServiceProvider::shouldAutoMigrate())->toBeFalse();
    expect(ium()->accounting()->shouldAutoMigrate())->toBeFalse();
});

it('disables auto migration via flat ium.accounting.auto_migrate configuration', function () {
    config([
        'ium.accounting.migrations.auto_migrate' => null,
        'ium.accounting.auto_migrate' => false,
    ]);

    expect(ObelawAccountingServiceProvider::shouldAutoMigrate())->toBeFalse();
});

it('disables auto migration via ObelawConfigManager global switch', function () {
    config([
        'ium.accounting.migrations.auto_migrate' => null,
        'ium.accounting.auto_migrate' => null,
    ]);

    ObelawConfigManager::getInstance()->set('obelawium.db.migrations', false);
    expect(ObelawAccountingServiceProvider::shouldAutoMigrate())->toBeFalse();

    ObelawConfigManager::getInstance()->set('obelawium.db.migrations', true);
    expect(ObelawAccountingServiceProvider::shouldAutoMigrate())->toBeTrue();

    ObelawConfigManager::getInstance()->reset();
});

it('allows programmatic override of auto migration via ServiceProvider and Domain gateway', function () {
    ObelawAccountingServiceProvider::autoMigrate(false);
    expect(ObelawAccountingServiceProvider::shouldAutoMigrate())->toBeFalse();

    ObelawAccountingServiceProvider::ignoreMigrations();
    expect(ObelawAccountingServiceProvider::shouldAutoMigrate())->toBeFalse();

    ium()->accounting()->autoMigrate(true);
    expect(ObelawAccountingServiceProvider::shouldAutoMigrate())->toBeTrue();

    ium()->accounting()->ignoreMigrations();
    expect(ObelawAccountingServiceProvider::shouldAutoMigrate())->toBeFalse();
});

it('allows custom migration path via config', function () {
    $customPath = sys_get_temp_dir() . '/custom_migrations';

    config(['ium.accounting.migrations.path' => $customPath]);
    expect(ObelawAccountingServiceProvider::getMigrationPath())->toBe($customPath);
    expect(ium()->accounting()->getMigrationPath())->toBe($customPath);
});

it('allows custom migration path via flat config ium.accounting.migration_path', function () {
    $customPath = sys_get_temp_dir() . '/custom_migrations_flat';

    config([
        'ium.accounting.migrations.path' => null,
        'ium.accounting.migration_path' => $customPath,
    ]);
    expect(ObelawAccountingServiceProvider::getMigrationPath())->toBe($customPath);
});

it('allows programmatic custom migration path via ServiceProvider and Domain gateway', function () {
    $customPath = sys_get_temp_dir() . '/custom_programmatic';

    ObelawAccountingServiceProvider::setMigrationPath($customPath);
    expect(ObelawAccountingServiceProvider::getMigrationPath())->toBe($customPath);

    $domainPath = sys_get_temp_dir() . '/custom_domain';
    ium()->accounting()->setMigrationPath($domainPath);
    expect(ium()->accounting()->getMigrationPath())->toBe($domainPath);
    expect(ObelawAccountingServiceProvider::getMigrationPath())->toBe($domainPath);
});

it('executes ium:accounting:migrate console command with custom path', function () {
    $exitCode = Artisan::call('ium:accounting:migrate', [
        '--path' => ObelawAccountingServiceProvider::defaultMigrationPath(),
    ]);

    expect($exitCode)->toBe(0);
});

it('executes domain migrate method programmatically', function () {
    $exitCode = ium()->accounting()->migrate(ObelawAccountingServiceProvider::defaultMigrationPath());

    expect($exitCode)->toBe(0);
});
