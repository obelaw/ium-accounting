<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Obelaw\Ium\Accounting\Demo\AccountingDemoSeeder;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\CostCenter;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FiscalYear;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Services\LedgerService;

class SeedAccountingDemoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ium:accounting:demo-seed
                            {--fresh : Wipe existing accounting domain tables before seeding}
                            {--force : Force execution without interactive confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed comprehensive financial demo data for Obelawium Accounting (COA, Fiscal Calendar, Cost Centers, Multi-Currency, ACL, and Balanced Vouchers)';

    /**
     * Execute the console command.
     */
    public function handle(AccountingDemoSeeder $seeder, LedgerService $ledgerService): int
    {
        $this->output->newLine();
        $this->line('<fg=cyan;options=bold>╔═══════════════════════════════════════════════════════════════════════════════╗</>');
        $this->line('<fg=cyan;options=bold>║               Obelawium Accounting (FMS) Demo Data Seeder                     ║</>');
        $this->line('<fg=cyan;options=bold>╚═══════════════════════════════════════════════════════════════════════════════╝</>');
        $this->output->newLine();

        if ($this->option('fresh')) {
            if (!$this->option('force') && !$this->confirm('This will wipe all existing accounting tables. Proceed?', false)) {
                $this->warn('Operation cancelled.');

                return self::SUCCESS;
            }

            $this->wipeAccountingData();
        }

        $this->info('Starting Obelawium Accounting demo seeding sequence...');
        $this->output->newLine();

        // Step 1: Currencies
        $this->task('1. Seeding ISO 4217 Currencies & Historical FX Rates', function () use ($seeder) {
            $seeder->seedCurrencies();
        });

        // Step 2: Fiscal Calendar
        $this->task('2. Seeding Fiscal Year 2026 & 12 Monthly Fiscal Periods', function () use ($seeder) {
            $seeder->seedFiscalCalendar();
        });

        // Step 3: Cost Centers
        $this->task('3. Seeding Analytical Cost Centers & Project Dimensions', function () use ($seeder) {
            $seeder->seedCostCenters();
        });

        // Step 4: COA
        $this->task('4. Seeding 4-Tier Hierarchical Chart of Accounts (COA)', function () use ($seeder) {
            $seeder->seedChartOfAccounts();
        });

        // Step 5: ACL Mappings
        $this->task('5. Seeding Anti-Corruption Layer (ACL) Event Mappings', function () use ($seeder) {
            $seeder->seedEventMappings();
        });

        // Step 6: Balanced Transactions
        $this->task('6. Drafting, Approving, and Posting Balanced Financial Vouchers', function () use ($seeder) {
            $seeder->seedTransactions();
        });

        // Step 7: Parity Verification
        $tb = null;
        $bs = null;
        $this->task('7. Verifying General Ledger Zero-Sum Parity & Balance Sheet', function () use ($ledgerService, &$tb, &$bs) {
            $tb = $ledgerService->generateTrialBalance();
            $bs = $ledgerService->generateBalanceSheet();

            if (!$tb['is_balanced'] || $tb['imbalance'] !== 0) {
                throw new \DomainException("Trial Balance imbalance detected: {$tb['imbalance']} cents");
            }

            if (!$bs['is_balanced']) {
                throw new \DomainException("Balance Sheet disparity detected: Assets != Liabilities + Equity");
            }
        });

        $this->output->newLine();
        $this->displaySummary($tb, $bs);

        $this->output->newLine();
        $this->info('✓ Accounting demo data successfully seeded and verified!');

        return self::SUCCESS;
    }

    /**
     * Wipe existing accounting tables safely.
     */
    private function wipeAccountingData(): void
    {
        $this->warn('Wiping existing accounting records...');

        $tables = [
            'ium_accounting_gl_postings',
            'ium_accounting_journal_entry_lines',
            'ium_accounting_journal_entries',
            'ium_accounting_audit_logs',
            'ium_accounting_event_account_mappings',
            'ium_accounting_accounts',
            'ium_accounting_cost_centers',
            'ium_accounting_fiscal_periods',
            'ium_accounting_fiscal_years',
            'ium_accounting_exchange_rates',
            'ium_accounting_currencies',
        ];

        Schema::disableForeignKeyConstraints();
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
        Schema::enableForeignKeyConstraints();

        $this->line('  <fg=gray>All ium_accounting_* tables truncated.</>');
    }

    /**
     * Render a formatted summary table of seeded entities.
     */
    private function displaySummary(?array $tb, ?array $bs): void
    {
        $currenciesCount = Currency::count();
        $periodsCount = FiscalPeriod::count();
        $costCentersCount = CostCenter::count();
        $accountsCount = Account::count();
        $postingAccounts = Account::postingAccounts()->count();
        $vouchersCount = JournalEntry::count();
        $postedCount = JournalEntry::where('status', JournalEntryStatus::POSTED)->count();
        $pendingCount = JournalEntry::where('status', JournalEntryStatus::PENDING_APPROVAL)->count();
        $draftCount = JournalEntry::where('status', JournalEntryStatus::DRAFT)->count();
        $postingsCount = GeneralLedgerPosting::count();

        $totalDebitMajor = $tb ? '$ ' . number_format($tb['total_debit_major'], 2) : 'N/A';
        $totalCreditMajor = $tb ? '$ ' . number_format($tb['total_credit_major'], 2) : 'N/A';
        $assetsMajor = $bs ? '$ ' . number_format($bs['assets']['total_major'], 2) : 'N/A';
        $liabEquityMajor = $bs ? '$ ' . number_format($bs['total_liabilities_and_equity_major'], 2) : 'N/A';

        $this->table(
            ['Domain Entity', 'Seeded Metric / Balance', 'Governance / Verification Status'],
            [
                ['ISO 4217 Currencies', "{$currenciesCount} registered (Base: USD)", '<fg=green>Active</>'],
                ['Fiscal Calendar', "1 Year (FY 2026), {$periodsCount} Monthly Periods", '<fg=green>OPEN</>'],
                ['Cost Centers', "{$costCentersCount} analytical dimensions (Admin, Eng, Sales, Ops)", '<fg=green>Active</>'],
                ['Chart of Accounts', "{$accountsCount} total ({$postingAccounts} leaf posting accounts)", '<fg=green>Hierarchical 4-Tier</>'],
                ['Vouchers (Journal Entries)', "{$vouchersCount} total ({$postedCount} posted, {$pendingCount} pending, {$draftCount} draft)", '<fg=green>Maker-Checker Verified</>'],
                ['General Ledger Postings', "{$postingsCount} immutable journal postings", '<fg=green>Immutable</>'],
                ['Trial Balance Parity', "Debits: {$totalDebitMajor} | Credits: {$totalCreditMajor}", '<fg=green>PARITY (0 cents imbalance)</>'],
                ['Balance Sheet Parity', "Assets: {$assetsMajor} | Liab + Eq: {$liabEquityMajor}", '<fg=green>BALANCED (A = L + E)</>'],
            ]
        );
    }

    /**
     * Console task helper.
     */
    protected function task(string $description, callable $task): void
    {
        $this->output->write("  {$description} ... ");

        try {
            $task();
            $this->output->writeln('<fg=green;options=bold>DONE</>');
        } catch (\Throwable $e) {
            $this->output->writeln('<fg=red;options=bold>FAILED</>');
            $this->error("    {$e->getMessage()}");
            throw $e;
        }
    }
}
