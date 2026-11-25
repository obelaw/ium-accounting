<p align="center"><img src="./cover.svg" alt="Obelaw IUM Accounting"/></p>

# Obelaw IUM Accounting (FMS) `v1.0.0`

An enterprise-grade, headless **General Ledger & Financial Management System (FMS)** domain module built natively on **`obelaw/ium` `^1.0`** using a clean **CQRS-lite (`Actions` + `Queries` + `Typed Report DTOs`)** architecture.

---

## Requirements

- **PHP** `^8.2`
- **Laravel** `^11.0 | ^12.0 | ^13.0`
- **Obelaw IUM Core** `obelaw/ium` `^1.0`

---

## Architecture Highlights

- **Pure `IUM 1.0` Primitives:** Every write operation is a focused `Obelaw\Ium\Actions\Action` with automatic `Ium::authorizer()` checks, `Ium::actor()` resolution, tenant-scoped `Ium::connection()->transaction()`, forensic audit hooks, and `afterCommit()` event dispatch.
- **Zero Floating-Point Money:** All monetary amounts are stored and calculated in integer minor units (`bigInteger`), parsed via `Obelaw\Ium\Support\Money`, with Largest-Remainder `Money::allocate()` for multi-line FX entries and integer basis-points (`rate_bps`) for taxes and depreciation.
- **Database-per-Tenant Ready:** Registered via `Module::make('accounting', AccountingDomain::class, ModuleScope::Tenant)` and verified by `Obelaw\Ium\Testing\ModuleTestCase` in both single-tenant and dual-tenant modes.

---

## Domain Gateways (`ium()->accounting()`)

| Gateway | Capabilities |
| :--- | :--- |
| `currencies()` | ISO 4217 registry, base currency switching, daily/historical FX rates, cross-rate triangulation, minor-unit conversion. |
| `accounts()` | 4-tier Chart of Accounts, leaf-only enforcement, reconcilable AR/AP flags, contra-account awareness, balance recalculation. |
| `journals()` | Dedicated journals (`GJ`, `SJ`, `PJ`, `BNK`, `CSH`, `FA`, `FX`) with independent `Ium::sequence()` numbering & default accounts. |
| `costCenters()` | Analytical dimensions (Cost Centers, Departments, Projects, Business Units). |
| `fiscalPeriods()` | Fiscal years (with automatic 12-month period generation), soft-close, hard-lock, and audited reopen. |
| `taxes()` | VAT & Withholding Tax definitions (`percentage` in basis points / `fixed`, `inclusive` / `exclusive`) and exact `TaxCalculator`. |
| `journalEntries()` | `createDraft`, `updateDraft`, `deleteDraft`, `submit`, `approve`, `reject`, `post` (strict Maker-Checker), and `reverse` (Storno). |
| `reconciliations()` | Partial & full open-item matching on reconcilable accounts with automatic **Realized FX Gain/Loss** GL entries and `unreconcile()`. |
| `fx()` | Period-end **Unrealized FX Revaluation** (IAS 21) with optional next-period auto-reversing entry. |
| `fixedAssets()` | Straight-Line & Declining-Balance depreciation schedules, batch period depreciation runs, and **Disposal with automatic Gain/Loss GL entry**. |
| `closing()` | Fiscal-year-scoped nominal account closing (with contra-revenue/expense support) into Retained Earnings + full year/period seal. |
| `reports()` | Typed immutable Report DTOs: `trialBalance()` (Opening/Period/Ending), `balanceSheet(asOfDate)`, `incomeStatement(from, to, costCenterId)`, `accountLedger()`, `partnerLedger()`, `aging()` (Current / 1–30 / 31–60 / 61–90 / 90+), and `vatReturn()`. |
| `acl()` | Posting rules, compound multi-line idempotent ingestion (`idempotency_key` unique index), and `Outbox` consumer (`ingestFromOutbox()`). |
| `audit()` | Immutable append-only forensic audit trail. |

---

## Quick Start

```bash
composer require obelaw/ium-accounting

# Single-tenant
php artisan migrate
# or
php artisan ium:migrate --module=accounting

# Multi-tenant (with obelaw/ium-tenancy)
php artisan ium:tenants:migrate --module=accounting
```

```php
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\PostingSide;

$entry = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
    entryDate: '2026-05-01',
    journalCode: 'SJ',
    narration: 'Sales Invoice #1001',
    lines: [
        JournalLineData::fromMajor($arAccount->id, PostingSide::DEBIT, '1150.00', 'USD', partyType: 'customer', partyId: 42, dueDate: '2026-05-31'),
        JournalLineData::fromMajor($salesAccount->id, PostingSide::CREDIT, '1000.00', 'USD'),
        JournalLineData::fromMajor($vatAccount->id, PostingSide::CREDIT, '150.00', 'USD', taxId: $vat15->id),
    ],
));

ium()->accounting()->journalEntries()->submit($entry->id, 'maker-1');
ium()->accounting()->journalEntries()->approve($entry->id, 'checker-1');
ium()->accounting()->journalEntries()->post($entry->id, 'checker-1');

$tb = ium()->accounting()->reports()->trialBalance(fromDate: '2026-05-01', toDate: '2026-05-31');
$bs = ium()->accounting()->reports()->balanceSheet(asOfDate: '2026-05-31');
$aging = ium()->accounting()->reports()->aging($arAccount->id, asOfDate: '2026-06-15');
```

---

## Testing

```bash
php vendor/bin/pest
```

---

## License

MIT License — see [LICENSE](LICENSE).
