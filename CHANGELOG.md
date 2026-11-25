# Changelog

All notable changes to `obelaw/ium-accounting` will be documented in this file.

## v1.0.0 - 2026-11-25

### Clean-Slate Enterprise Architecture on `obelaw/ium` `^1.0`
- Rebuilt module on CQRS-lite **Actions** (`Obelaw\Ium\Actions\Action`) + **Queries** + **Typed immutable Report DTOs** + thin **Domain Gateways** (`ium()->accounting()->*`).
- Registered module via `Module::make('accounting', AccountingDomain::class, ModuleScope::Tenant)` with full `ModuleTestCase` single-tenant and dual-tenant conformance.
- Eliminated duplicate float-based `Money` value object in favor of `Obelaw\Ium\Support\Money` (integer minor units + exact string parsing + Largest-Remainder `allocate()`).
- Added **Journals** (`GJ`, `SJ`, `PJ`, `BNK`, `CSH`, `FA`, `FX`) with per-journal `Ium::sequence()` numbering.
- Added draft editing (`updateDraft`) and deletion (`deleteDraft`), strict Maker-Checker enforcement, Storno reversals, and hardened immutability guards across `JournalEntry`, `JournalEntryLine`, `GeneralLedgerPosting`, and `AccountingAuditLog`.
- Added **Open-Item Reconciliation Engine** (`reconcile`, `unreconcile`, `openItems`) with automatic **Realized FX Gain/Loss** GL posting on foreign-currency settlement.
- Added **Period-End Unrealized FX Revaluation** (`fx()->revalue()`) with optional auto-reversing entry.
- Added **Tax / VAT Engine** (`Tax`, integer basis-point `TaxCalculator` supporting exclusive, inclusive, fixed, withholding, and compound groups) and **VAT Return Report**.
- Upgraded **Fixed Assets** with both Straight-Line & Declining-Balance depreciation schedules and **automatic Disposal Gain/Loss GL posting**.
- Built full **Typed Financial Reporting Suite**: Opening/Period/Ending Trial Balance, Point-in-Time Balance Sheet (`asOfDate`), Date-Range & Cost-Center Income Statement, Account Ledger with Opening Balance, Partner Ledger, AR/AP Aging Report (5 buckets), and VAT Return Report.
- Upgraded **ACL / Posting Rules** with database-enforced unique `idempotency_key`, multi-line postings, and `Outbox` ingestion (`ingestFromOutbox()`).
