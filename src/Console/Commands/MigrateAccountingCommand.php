<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Obelaw\Ium\Accounting\Providers\ObelawAccountingServiceProvider;

class MigrateAccountingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ium:accounting:migrate
                            {--path= : Specify a custom migration path to run instead of the default package migrations}
                            {--database= : The database connection to use for migrations}
                            {--force : Force execution without interactive confirmation in production}
                            {--pretend : Dump the SQL queries that would be run}
                            {--step : Force the migrations to be run so they can be rolled back individually}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run database migrations for Obelawium Accounting, with support for custom migration paths';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->output->newLine();
        $this->line('<fg=cyan;options=bold>╔═══════════════════════════════════════════════════════════════════════════════╗</>');
        $this->line('<fg=cyan;options=bold>║               Obelawium Accounting (FMS) Migrations Runner                   ║</>');
        $this->line('<fg=cyan;options=bold>╚═══════════════════════════════════════════════════════════════════════════════╝</>');
        $this->output->newLine();

        $customPath = $this->option('path');
        $database = $this->option('database') ?: config('ium.accounting.connection');
        $force = (bool) $this->option('force');
        $pretend = (bool) $this->option('pretend');
        $step = (bool) $this->option('step');

        $migrationPath = ! empty($customPath)
            ? (string) $customPath
            : ObelawAccountingServiceProvider::getMigrationPath();

        $resolvedPath = realpath($migrationPath) ?: $migrationPath;

        $this->info("Target migrations directory: [{$resolvedPath}]");

        $params = [
            '--path' => $resolvedPath,
            '--realpath' => true,
        ];

        if ($database) {
            $params['--database'] = $database;
        }

        if ($force) {
            $params['--force'] = true;
        }

        if ($pretend) {
            $params['--pretend'] = true;
        }

        if ($step) {
            $params['--step'] = true;
        }

        $exitCode = $this->call('migrate', $params);

        if ($exitCode === self::SUCCESS) {
            $this->output->newLine();
            $this->info('✓ Obelawium Accounting migrations executed successfully.');
        } else {
            $this->output->newLine();
            $this->error('Failed to execute Obelawium Accounting migrations.');
        }

        return $exitCode;
    }
}
