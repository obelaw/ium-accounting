<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Demo;

use Illuminate\Database\Seeder;
use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateCostCenterDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalPeriodDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalYearDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Data\JournalEntryLineDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\DimensionType;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\CostCenter;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FiscalYear;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\AclIngestionService;
use Obelaw\Ium\Accounting\Services\CostCenterService;
use Obelaw\Ium\Accounting\Services\CurrencyService;
use Obelaw\Ium\Accounting\Services\FiscalPeriodService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;
use Obelaw\Ium\Accounting\Services\LedgerService;

class AccountingDemoSeeder extends Seeder
{
    private AccountService $accountService;
    private FiscalPeriodService $fiscalPeriodService;
    private CostCenterService $costCenterService;
    private CurrencyService $currencyService;
    private JournalEntryService $journalEntryService;
    private AclIngestionService $aclService;
    private LedgerService $ledgerService;

    public function __construct(
        ?AccountService $accountService = null,
        ?FiscalPeriodService $fiscalPeriodService = null,
        ?CostCenterService $costCenterService = null,
        ?CurrencyService $currencyService = null,
        ?JournalEntryService $journalEntryService = null,
        ?AclIngestionService $aclService = null,
        ?LedgerService $ledgerService = null,
    ) {
        $this->accountService = $accountService ?? app(AccountService::class);
        $this->fiscalPeriodService = $fiscalPeriodService ?? app(FiscalPeriodService::class);
        $this->costCenterService = $costCenterService ?? app(CostCenterService::class);
        $this->currencyService = $currencyService ?? app(CurrencyService::class);
        $this->journalEntryService = $journalEntryService ?? app(JournalEntryService::class);
        $this->aclService = $aclService ?? app(AclIngestionService::class);
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Run the comprehensive accounting demo seed.
     */
    public function run(): void
    {
        $this->seedCurrencies();
        $this->seedFiscalCalendar();
        $this->seedCostCenters();
        $this->seedChartOfAccounts();
        $this->seedEventMappings();
        $this->seedTransactions();
    }

    /**
     * 1. Seed ISO 4217 Currencies and Historical Exchange Rates.
     */
    public function seedCurrencies(): void
    {
        // Standard international ISO currencies (USD base, EUR, GBP, SAR, AED, KWD, EGP)
        $this->currencyService->seedStandardCurrencies('USD');

        // Historical & effective exchange rate snapshots
        $rates = [
            ['EUR', 1.085000, '2026-01-01', 'ecb_benchmark'],
            ['EUR', 1.090000, '2026-01-25', 'ecb_benchmark'],
            ['GBP', 1.280000, '2026-01-01', 'boe_benchmark'],
            ['SAR', 0.266667, '2026-01-01', 'sama_peg'],
            ['AED', 0.272257, '2026-01-01', 'cbuae_peg'],
            ['KWD', 3.260000, '2026-01-01', 'cbk_basket'],
            ['EGP', 0.021000, '2026-01-01', 'cbe_market'],
        ];

        foreach ($rates as [$code, $rate, $date, $source]) {
            if (Currency::where('code', $code)->exists()) {
                $this->currencyService->setRate(
                    code: $code,
                    rate: $rate,
                    date: $date,
                    source: $source,
                    actorId: 'system-seeder'
                );
            }
        }
    }

    /**
     * 2. Seed Fiscal Year 2026 with 12 Monthly Fiscal Periods.
     */
    public function seedFiscalCalendar(): FiscalYear
    {
        $year = FiscalYear::where('name', 'FY 2026')->first();

        if ($year === null) {
            $year = $this->fiscalPeriodService->createYear(new CreateFiscalYearDto(
                name: 'FY 2026',
                startDate: '2026-01-01',
                endDate: '2026-12-31',
                status: FiscalPeriodStatus::OPEN,
                actorId: 'cfo-user'
            ));
        }

        $months = [
            1 => ['January 2026', '2026-01-01', '2026-01-31'],
            2 => ['February 2026', '2026-02-01', '2026-02-28'],
            3 => ['March 2026', '2026-03-01', '2026-03-31'],
            4 => ['April 2026', '2026-04-01', '2026-04-30'],
            5 => ['May 2026', '2026-05-01', '2026-05-31'],
            6 => ['June 2026', '2026-06-01', '2026-06-30'],
            7 => ['July 2026', '2026-07-01', '2026-07-31'],
            8 => ['August 2026', '2026-08-01', '2026-08-31'],
            9 => ['September 2026', '2026-09-01', '2026-09-30'],
            10 => ['October 2026', '2026-10-01', '2026-10-31'],
            11 => ['November 2026', '2026-11-01', '2026-11-30'],
            12 => ['December 2026', '2026-12-01', '2026-12-31'],
        ];

        foreach ($months as $num => [$name, $start, $end]) {
            if (!FiscalPeriod::where('fiscal_year_id', $year->id)->where('period_number', $num)->exists()) {
                $this->fiscalPeriodService->createPeriod(new CreateFiscalPeriodDto(
                    fiscalYearId: $year->id,
                    periodNumber: $num,
                    name: $name,
                    startDate: $start,
                    endDate: $end,
                    status: FiscalPeriodStatus::OPEN,
                    actorId: 'cfo-user'
                ));
            }
        }

        return $year;
    }

    /**
     * 3. Seed Analytical Cost Centers and Project Dimensions.
     */
    public function seedCostCenters(): void
    {
        $centers = [
            ['CC-ADMIN', 'General & Administrative', DimensionType::DEPARTMENT, 'Administrative overhead'],
            ['CC-ENG', 'Engineering & Product', DimensionType::DEPARTMENT, 'R&D, cloud hosting, and software delivery'],
            ['CC-SALES', 'Sales & Marketing', DimensionType::DEPARTMENT, 'Enterprise sales pipeline and acquisition'],
            ['CC-OPS', 'Customer Operations & Support', DimensionType::DEPARTMENT, 'Client onboarding and client care'],
            ['PRJ-ALPHA', 'Enterprise ERP Modernization', DimensionType::PROJECT, 'Multi-tenant cloud core overhaul'],
            ['PRJ-BETA', 'Mobile Banking Platform', DimensionType::PROJECT, 'Native iOS & Android digital experience'],
        ];

        foreach ($centers as [$code, $name, $type, $desc]) {
            if (!CostCenter::where('code', $code)->exists()) {
                $this->costCenterService->create(new CreateCostCenterDto(
                    code: $code,
                    name: $name,
                    dimensionType: $type,
                    isActive: true,
                    actorId: 'admin-user',
                    metadata: ['description' => $desc]
                ));
            }
        }
    }

    /**
     * 4. Seed 4-Tier Hierarchical Chart of Accounts (COA).
     */
    public function seedChartOfAccounts(): void
    {
        if (Account::where('code', '1000')->exists()) {
            return;
        }

        // ==========================================
        // 1. ASSETS (1000)
        // ==========================================
        $assets = $this->createAccount('1000', 'Assets', AccountType::ASSET, AccountClassification::CLASS_ROOT, NormalBalance::DEBIT, null, false);

        // 1.1 Current Assets
        $curAssets = $this->createAccount('1100', 'Current Assets', AccountType::ASSET, AccountClassification::MAIN_GROUP, NormalBalance::DEBIT, $assets->id, false);

        $cashGroup = $this->createAccount('1110', 'Cash & Cash Equivalents', AccountType::ASSET, AccountClassification::SUB_GROUP, NormalBalance::DEBIT, $curAssets->id, false);
        $this->createAccount('1111', 'Operating Bank Account (USD)', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::DEBIT, $cashGroup->id, true, 'USD', true);
        $this->createAccount('1112', 'European Operating Account (EUR)', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::DEBIT, $cashGroup->id, true, 'EUR', true);
        $this->createAccount('1113', 'Petty Cash Fund', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::DEBIT, $cashGroup->id, true);

        $arGroup = $this->createAccount('1120', 'Trade Receivables', AccountType::ASSET, AccountClassification::SUB_GROUP, NormalBalance::DEBIT, $curAssets->id, false);
        $this->createAccount('1200', 'Accounts Receivable Control (USD)', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::DEBIT, $arGroup->id, true);
        $this->createAccount('1201', 'Accounts Receivable (EUR)', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::DEBIT, $arGroup->id, true, 'EUR');
        $this->createAccount('1290', 'Allowance for Doubtful Accounts', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::CREDIT, $arGroup->id, true);

        $invGroup = $this->createAccount('1130', 'Inventories', AccountType::ASSET, AccountClassification::SUB_GROUP, NormalBalance::DEBIT, $curAssets->id, false);
        $this->createAccount('1300', 'Merchandise Finished Goods', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::DEBIT, $invGroup->id, true);
        $this->createAccount('1310', 'Goods in Transit', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::DEBIT, $invGroup->id, true);

        $prepGroup = $this->createAccount('1140', 'Prepayments & Deposits', AccountType::ASSET, AccountClassification::SUB_GROUP, NormalBalance::DEBIT, $curAssets->id, false);
        $this->createAccount('1400', 'Prepaid Insurance & Leases', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::DEBIT, $prepGroup->id, true);

        // 1.2 Non-Current Fixed Assets
        $fixedAssets = $this->createAccount('1500', 'Property, Plant & Equipment', AccountType::ASSET, AccountClassification::MAIN_GROUP, NormalBalance::DEBIT, $assets->id, false);
        $ppeGroup = $this->createAccount('1510', 'Hardware & Office Equipment', AccountType::ASSET, AccountClassification::SUB_GROUP, NormalBalance::DEBIT, $fixedAssets->id, false);
        $this->createAccount('1511', 'IT & Server Infrastructure', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::DEBIT, $ppeGroup->id, true);
        $this->createAccount('1512', 'Office Furniture & Fixtures', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::DEBIT, $ppeGroup->id, true);
        $this->createAccount('1590', 'Accumulated Depreciation', AccountType::ASSET, AccountClassification::POSTING, NormalBalance::CREDIT, $ppeGroup->id, true);

        // ==========================================
        // 2. LIABILITIES (2000)
        // ==========================================
        $liabilities = $this->createAccount('2000', 'Liabilities', AccountType::LIABILITY, AccountClassification::CLASS_ROOT, NormalBalance::CREDIT, null, false);

        $curLiab = $this->createAccount('2100', 'Current Liabilities', AccountType::LIABILITY, AccountClassification::MAIN_GROUP, NormalBalance::CREDIT, $liabilities->id, false);

        $apGroup = $this->createAccount('2110', 'Trade Payables', AccountType::LIABILITY, AccountClassification::SUB_GROUP, NormalBalance::CREDIT, $curLiab->id, false);
        $this->createAccount('2001', 'Accounts Payable Control (USD)', AccountType::LIABILITY, AccountClassification::POSTING, NormalBalance::CREDIT, $apGroup->id, true);
        $this->createAccount('2010', 'Goods Received Not Invoiced (GRNI)', AccountType::LIABILITY, AccountClassification::POSTING, NormalBalance::CREDIT, $apGroup->id, true);

        $taxGroup = $this->createAccount('2120', 'Accruals & Statutory Tax', AccountType::LIABILITY, AccountClassification::SUB_GROUP, NormalBalance::CREDIT, $curLiab->id, false);
        $this->createAccount('2121', 'Accrued Payroll & Salaries Payable', AccountType::LIABILITY, AccountClassification::POSTING, NormalBalance::CREDIT, $taxGroup->id, true);
        $this->createAccount('2210', 'Output VAT / Sales Tax Payable', AccountType::LIABILITY, AccountClassification::POSTING, NormalBalance::CREDIT, $taxGroup->id, true);
        $this->createAccount('2220', 'Input VAT Recoverable', AccountType::LIABILITY, AccountClassification::POSTING, NormalBalance::DEBIT, $taxGroup->id, true);

        // ==========================================
        // 3. EQUITY (3000)
        // ==========================================
        $equity = $this->createAccount('3000', 'Shareholders Equity', AccountType::EQUITY, AccountClassification::CLASS_ROOT, NormalBalance::CREDIT, null, false);
        $eqGroup = $this->createAccount('3100', 'Paid-in Capital & Earnings', AccountType::EQUITY, AccountClassification::MAIN_GROUP, NormalBalance::CREDIT, $equity->id, false);
        $this->createAccount('3010', 'Common Stock / Contributed Capital', AccountType::EQUITY, AccountClassification::POSTING, NormalBalance::CREDIT, $eqGroup->id, true);
        $this->createAccount('3020', 'Retained Earnings', AccountType::EQUITY, AccountClassification::POSTING, NormalBalance::CREDIT, $eqGroup->id, true);

        // ==========================================
        // 4. REVENUE (4000)
        // ==========================================
        $revenue = $this->createAccount('4000', 'Revenue', AccountType::REVENUE, AccountClassification::CLASS_ROOT, NormalBalance::CREDIT, null, false);

        $opRev = $this->createAccount('4100', 'Operating Revenue', AccountType::REVENUE, AccountClassification::MAIN_GROUP, NormalBalance::CREDIT, $revenue->id, false);
        $this->createAccount('4010', 'SaaS Subscription Revenue', AccountType::REVENUE, AccountClassification::POSTING, NormalBalance::CREDIT, $opRev->id, true);
        $this->createAccount('4020', 'Professional & Consulting Services', AccountType::REVENUE, AccountClassification::POSTING, NormalBalance::CREDIT, $opRev->id, true);
        $this->createAccount('4030', 'Hardware & License Sales', AccountType::REVENUE, AccountClassification::POSTING, NormalBalance::CREDIT, $opRev->id, true);

        $finInc = $this->createAccount('4900', 'Financial & Other Income', AccountType::REVENUE, AccountClassification::MAIN_GROUP, NormalBalance::CREDIT, $revenue->id, false);
        $this->createAccount('4910', 'Realized Foreign Exchange Gain', AccountType::REVENUE, AccountClassification::POSTING, NormalBalance::CREDIT, $finInc->id, true);

        // ==========================================
        // 5. COST OF GOODS SOLD (5000)
        // ==========================================
        $cogs = $this->createAccount('5000', 'Cost of Goods Sold', AccountType::EXPENSE, AccountClassification::CLASS_ROOT, NormalBalance::DEBIT, null, false);
        $dirCost = $this->createAccount('5100', 'Direct Operational Infrastructure', AccountType::EXPENSE, AccountClassification::MAIN_GROUP, NormalBalance::DEBIT, $cogs->id, false);
        $this->createAccount('5010', 'Cloud Hosting & CDN Infrastructure', AccountType::EXPENSE, AccountClassification::POSTING, NormalBalance::DEBIT, $dirCost->id, true);
        $this->createAccount('5020', 'Third-Party API & Data Subscriptions', AccountType::EXPENSE, AccountClassification::POSTING, NormalBalance::DEBIT, $dirCost->id, true);

        // ==========================================
        // 6. OPERATING EXPENSES (6000)
        // ==========================================
        $opex = $this->createAccount('6000', 'Operating Expenses', AccountType::EXPENSE, AccountClassification::CLASS_ROOT, NormalBalance::DEBIT, null, false);

        $staffExp = $this->createAccount('6100', 'Personnel & Staff Expenses', AccountType::EXPENSE, AccountClassification::MAIN_GROUP, NormalBalance::DEBIT, $opex->id, false);
        $this->createAccount('6010', 'Engineering & Staff Salaries', AccountType::EXPENSE, AccountClassification::POSTING, NormalBalance::DEBIT, $staffExp->id, true);
        $this->createAccount('6015', 'Health Insurance & Employee Benefits', AccountType::EXPENSE, AccountClassification::POSTING, NormalBalance::DEBIT, $staffExp->id, true);

        $facExp = $this->createAccount('6200', 'Facilities & Leases', AccountType::EXPENSE, AccountClassification::MAIN_GROUP, NormalBalance::DEBIT, $opex->id, false);
        $this->createAccount('6020', 'Office Rent & Facilities', AccountType::EXPENSE, AccountClassification::POSTING, NormalBalance::DEBIT, $facExp->id, true);

        $mktExp = $this->createAccount('6300', 'Sales & Growth Marketing', AccountType::EXPENSE, AccountClassification::MAIN_GROUP, NormalBalance::DEBIT, $opex->id, false);
        $this->createAccount('6030', 'Digital Advertising & Growth Campaigns', AccountType::EXPENSE, AccountClassification::POSTING, NormalBalance::DEBIT, $mktExp->id, true);

        $finExp = $this->createAccount('6900', 'Financial Charges & FX Loss', AccountType::EXPENSE, AccountClassification::MAIN_GROUP, NormalBalance::DEBIT, $opex->id, false);
        $this->createAccount('6910', 'Realized Foreign Exchange Loss', AccountType::EXPENSE, AccountClassification::POSTING, NormalBalance::DEBIT, $finExp->id, true);
        $this->createAccount('6920', 'Bank Fees & Merchant Processing Charges', AccountType::EXPENSE, AccountClassification::POSTING, NormalBalance::DEBIT, $finExp->id, true);
    }

    /**
     * 5. Seed Pre-Configured Anti-Corruption Layer (ACL) Event Mappings.
     */
    public function seedEventMappings(): void
    {
        $mappings = [
            ['billing.invoice.issued', '1200', '4010', 'Automatic posting for standard sales invoice'],
            ['billing.payment.received', '1111', '1200', 'Automatic clearance of receivable on customer payment'],
            ['inventory.goods.received', '1300', '2010', 'Inventory stock-in offset to GRNI clearing account'],
            ['wms.inventory.scrapped', '5010', '1300', 'Scrapped inventory write-off to direct operational costs'],
            ['payroll.salary.processed', '6010', '2121', 'Monthly payroll run accrual to salaries payable'],
        ];

        foreach ($mappings as [$event, $dr, $cr, $desc]) {
            $this->aclService->registerMapping(
                eventName: $event,
                debitAccountCode: $dr,
                creditAccountCode: $cr,
                descriptionTemplate: $desc
            );
        }
    }

    /**
     * 6. Seed Realistic Balanced Financial Vouchers in Integer Minor Units (Cents).
     */
    public function seedTransactions(): void
    {
        $bankUsd    = $this->accountService->findByCodeOrFail('1111');
        $bankEur    = $this->accountService->findByCodeOrFail('1112');
        $arUsd      = $this->accountService->findByCodeOrFail('1200');
        $arEur      = $this->accountService->findByCodeOrFail('1201');
        $itHw       = $this->accountService->findByCodeOrFail('1511');
        $prepaidRent= $this->accountService->findByCodeOrFail('1400');
        $apUsd      = $this->accountService->findByCodeOrFail('2001');
        $vatPayable = $this->accountService->findByCodeOrFail('2210');
        $capital    = $this->accountService->findByCodeOrFail('3010');
        $saasRev    = $this->accountService->findByCodeOrFail('4010');
        $fxGain     = $this->accountService->findByCodeOrFail('4910');
        $cloudCogs  = $this->accountService->findByCodeOrFail('5010');
        $salaries   = $this->accountService->findByCodeOrFail('6010');
        $mktExpense = $this->accountService->findByCodeOrFail('6030');
        $facilities = $this->accountService->findByCodeOrFail('6020');

        $ccAdmin = CostCenter::where('code', 'CC-ADMIN')->first()?->id;
        $ccEng   = CostCenter::where('code', 'CC-ENG')->first()?->id;
        $ccSales = CostCenter::where('code', 'CC-SALES')->first()?->id;
        $ccOps   = CostCenter::where('code', 'CC-OPS')->first()?->id;

        // -------------------------------------------------------------------------
        // Voucher 1: Initial Equity Contribution (Jan 1, 2026) -> $500,000.00
        // -------------------------------------------------------------------------
        $this->createAndPostVoucher(
            date: '2026-01-01',
            entryNumber: 'JV-2026-0001',
            narration: 'Initial founder equity contribution and seed capital',
            lines: [
                new JournalEntryLineDto(accountId: $bankUsd->id, type: PostingSide::DEBIT, amount: 50000000), // $500,000.00
                new JournalEntryLineDto(accountId: $capital->id, type: PostingSide::CREDIT, amount: 50000000),
            ]
        );

        // -------------------------------------------------------------------------
        // Voucher 2: IT Servers & Infrastructure Acquisition (Jan 5, 2026) -> $35,000.00
        // -------------------------------------------------------------------------
        $this->createAndPostVoucher(
            date: '2026-01-05',
            entryNumber: 'JV-2026-0002',
            narration: 'Purchase of dedicated servers and developer workstations',
            lines: [
                new JournalEntryLineDto(accountId: $itHw->id, type: PostingSide::DEBIT, amount: 3500000, costCenterId: $ccEng), // $35,000.00
                new JournalEntryLineDto(accountId: $bankUsd->id, type: PostingSide::CREDIT, amount: 3500000),
            ]
        );

        // -------------------------------------------------------------------------
        // Voucher 3: Prepaid Office Lease (Jan 8, 2026) -> $48,000.00
        // -------------------------------------------------------------------------
        $this->createAndPostVoucher(
            date: '2026-01-08',
            entryNumber: 'JV-2026-0003',
            narration: 'Prepayment of 12-month commercial office lease for 2026',
            lines: [
                new JournalEntryLineDto(accountId: $prepaidRent->id, type: PostingSide::DEBIT, amount: 4800000, costCenterId: $ccAdmin), // $48,000.00
                new JournalEntryLineDto(accountId: $bankUsd->id, type: PostingSide::CREDIT, amount: 4800000),
            ]
        );

        // -------------------------------------------------------------------------
        // Voucher 4: Enterprise Annual B2B Subscription with VAT (Jan 15, 2026) -> $27,500.00
        // -------------------------------------------------------------------------
        $this->createAndPostVoucher(
            date: '2026-01-15',
            entryNumber: 'JV-2026-0004',
            narration: 'Invoice #INV-2026-101: Enterprise annual tier + 10% Output VAT',
            lines: [
                new JournalEntryLineDto(accountId: $arUsd->id, type: PostingSide::DEBIT, amount: 2750000, partyType: 'customer', partyId: 101), // $27,500.00
                new JournalEntryLineDto(accountId: $saasRev->id, type: PostingSide::CREDIT, amount: 2500000, costCenterId: $ccSales),            // $25,000.00
                new JournalEntryLineDto(accountId: $vatPayable->id, type: PostingSide::CREDIT, amount: 250000),                                   // $2,500.00
            ]
        );

        // -------------------------------------------------------------------------
        // Voucher 5: Client Wire Settlement (Jan 20, 2026) -> $27,500.00
        // -------------------------------------------------------------------------
        $this->createAndPostVoucher(
            date: '2026-01-20',
            entryNumber: 'JV-2026-0005',
            narration: 'Wire payment collected for Invoice #INV-2026-101',
            lines: [
                new JournalEntryLineDto(accountId: $bankUsd->id, type: PostingSide::DEBIT, amount: 2750000), // $27,500.00
                new JournalEntryLineDto(accountId: $arUsd->id, type: PostingSide::CREDIT, amount: 2750000, partyType: 'customer', partyId: 101),
            ]
        );

        // -------------------------------------------------------------------------
        // Voucher 6: European Multi-Currency Sale in EUR (Jan 22, 2026) -> €15,000.00 at 1.085000
        // -------------------------------------------------------------------------
        $this->createAndPostVoucher(
            date: '2026-01-22',
            entryNumber: 'JV-2026-0006',
            narration: 'European client subscription #EUR-2026-042',
            lines: [
                new JournalEntryLineDto(accountId: $arEur->id, type: PostingSide::DEBIT, amount: 1500000, partyType: 'customer', partyId: 102), // 15,000.00 EUR
                new JournalEntryLineDto(accountId: $saasRev->id, type: PostingSide::CREDIT, amount: 1500000, costCenterId: $ccSales),
            ],
            currency: 'EUR',
            exchangeRate: 1.085000
        );

        // -------------------------------------------------------------------------
        // Voucher 7: EUR Wire Settlement with Realized FX Gain (Jan 28, 2026) -> Rate: 1.090000
        // -------------------------------------------------------------------------
        // Received 15,000 EUR in EUR Bank account = $16,350.00 functional (1,635,000 cents).
        // Original AR cleared at $16,275.00 functional (1,627,500 cents).
        // Realized FX Gain recognized = $75.00 (7,500 cents).
        $this->createAndPostVoucher(
            date: '2026-01-28',
            entryNumber: 'JV-2026-0007',
            narration: 'Settlement of EUR invoice #EUR-2026-042 with realized FX gain',
            lines: [
                new JournalEntryLineDto(accountId: $bankEur->id, type: PostingSide::DEBIT, amount: 1635000), // $16,350.00 functional
                new JournalEntryLineDto(accountId: $arEur->id, type: PostingSide::CREDIT, amount: 1627500, partyType: 'customer', partyId: 102), // $16,275.00 functional
                new JournalEntryLineDto(accountId: $fxGain->id, type: PostingSide::CREDIT, amount: 7500),    // $75.00 FX Gain
            ]
        );

        // -------------------------------------------------------------------------
        // Voucher 8: Cloud Infrastructure Monthly OPEX (Feb 1, 2026) -> $6,840.50
        // -------------------------------------------------------------------------
        $this->createAndPostVoucher(
            date: '2026-02-01',
            entryNumber: 'JV-2026-0008',
            narration: 'AWS Cloud hosting and production database cluster for January',
            lines: [
                new JournalEntryLineDto(accountId: $cloudCogs->id, type: PostingSide::DEBIT, amount: 684050, costCenterId: $ccEng), // $6,840.50
                new JournalEntryLineDto(accountId: $bankUsd->id, type: PostingSide::CREDIT, amount: 684050),
            ]
        );

        // -------------------------------------------------------------------------
        // Voucher 9: Staff Payroll Disbursement (Feb 5, 2026) -> $32,500.00
        // -------------------------------------------------------------------------
        $this->createAndPostVoucher(
            date: '2026-02-05',
            entryNumber: 'JV-2026-0009',
            narration: 'Bi-weekly engineering and operations payroll disbursement',
            lines: [
                new JournalEntryLineDto(accountId: $salaries->id, type: PostingSide::DEBIT, amount: 2400000, costCenterId: $ccEng), // $24,000.00
                new JournalEntryLineDto(accountId: $salaries->id, type: PostingSide::DEBIT, amount: 850000, costCenterId: $ccOps),   // $8,500.00
                new JournalEntryLineDto(accountId: $bankUsd->id, type: PostingSide::CREDIT, amount: 3250000),                         // $32,500.00
            ]
        );

        // -------------------------------------------------------------------------
        // Voucher 10: Pending Approval Marketing Voucher (Feb 10, 2026) -> $12,000.00
        // -------------------------------------------------------------------------
        $draftVoucher = $this->journalEntryService->createDraft(new CreateJournalEntryDto(
            entryDate: '2026-02-10',
            entryNumber: 'JV-2026-0010',
            currency: 'USD',
            creatorId: 'marketing-lead-1',
            lines: [
                new JournalEntryLineDto(accountId: $mktExpense->id, type: PostingSide::DEBIT, amount: 1200000, costCenterId: $ccSales), // $12,000.00
                new JournalEntryLineDto(accountId: $apUsd->id, type: PostingSide::CREDIT, amount: 1200000),
            ],
            narration: 'Q1 Digital advertising and growth acquisition campaign'
        ));

        // Submit for Maker-Checker approval
        $this->journalEntryService->submit($draftVoucher->id, 'marketing-lead-1');

        // -------------------------------------------------------------------------
        // Voucher 11: Draft Facilities Maintenance (Feb 12, 2026) -> $850.00
        // -------------------------------------------------------------------------
        $this->journalEntryService->createDraft(new CreateJournalEntryDto(
            entryDate: '2026-02-12',
            entryNumber: 'JV-2026-0011',
            currency: 'USD',
            creatorId: 'office-manager-1',
            lines: [
                new JournalEntryLineDto(accountId: $facilities->id, type: PostingSide::DEBIT, amount: 85000, costCenterId: $ccAdmin), // $850.00
                new JournalEntryLineDto(accountId: $apUsd->id, type: PostingSide::CREDIT, amount: 85000),
            ],
            narration: 'Annual HVAC maintenance and office sanitization'
        ));
    }

    /**
     * Helper to create, submit, approve, and post a voucher in compliance with Maker-Checker governance.
     */
    private function createAndPostVoucher(
        string $date,
        string $entryNumber,
        string $narration,
        array $lines,
        string $currency = 'USD',
        float $exchangeRate = 1.0,
    ): void {
        $creator = 'finance-clerk-1';
        $approver = 'finance-supervisor-2';

        $draft = $this->journalEntryService->createDraft(new CreateJournalEntryDto(
            entryDate: $date,
            entryNumber: $entryNumber,
            entryType: JournalEntryType::STANDARD,
            currency: $currency,
            exchangeRate: $exchangeRate,
            creatorId: $creator,
            lines: $lines,
            narration: $narration
        ));

        $submitted = $this->journalEntryService->submit($draft->id, $creator);
        $approved  = $this->journalEntryService->approve($submitted->id, $approver);
        $this->journalEntryService->post($approved->id, $approver);
    }

    /**
     * Helper to create an account if it doesn't already exist.
     */
    private function createAccount(
        string $code,
        string $name,
        AccountType $type,
        AccountClassification $classification,
        NormalBalance $normalBalance,
        ?int $parentId = null,
        bool $isLeaf = true,
        string $currency = 'USD',
        bool $isReconciliation = false,
    ): Account {
        return $this->accountService->create(new CreateAccountDto(
            code: $code,
            name: $name,
            type: $type,
            classification: $classification,
            normalBalance: $normalBalance,
            parentId: $parentId,
            currency: $currency,
            isLeaf: $isLeaf,
            isReconciliation: $isReconciliation,
            isActive: true,
            actorId: 'system-seeder'
        ));
    }
}
