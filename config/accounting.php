<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database Table Prefix
    |--------------------------------------------------------------------------
    |
    | Module-specific prefix appended after 'ium_' (resulting in 'ium_accounting_').
    |
    */
    'table_prefix' => 'accounting_',

    /*
    |--------------------------------------------------------------------------
    | Functional Currency & Precision
    |--------------------------------------------------------------------------
    |
    | The base reporting currency and decimal precision used for intermediate
    | allocations and financial statement rounding.
    |
    */
    'currency' => [
        'functional' => env('IUM_ACCOUNTING_FUNCTIONAL_CURRENCY', 'USD'),
        'precision' => (int) env('IUM_ACCOUNTING_DECIMAL_PRECISION', 4),
        'rounding_mode' => env('IUM_ACCOUNTING_ROUNDING_MODE', 'half_up'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maker-Checker Governance
    |--------------------------------------------------------------------------
    |
    | When strict mode is enabled, the approver of a journal voucher must be
    | a different actor from the voucher's creator.
    |
    */
    'maker_checker' => [
        'strict' => (bool) env('IUM_ACCOUNTING_MAKER_CHECKER_STRICT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fiscal Year-End Closing Accounts
    |--------------------------------------------------------------------------
    |
    | Default nominal clearing accounts for year-end income statement zeroing
    | and balance sheet retained earnings transfer.
    |
    */
    'closing' => [
        'retained_earnings_account_code' => env('IUM_ACCOUNTING_RETAINED_EARNINGS_CODE', '3100'),
        'income_summary_account_code' => env('IUM_ACCOUNTING_INCOME_SUMMARY_CODE', '3900'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Trail
    |--------------------------------------------------------------------------
    |
    | Append-only logging of all state transitions, approvals, reversals, and
    | period locks for statutory compliance and forensic accounting.
    |
    */
    'audit' => [
        'enabled' => (bool) env('IUM_ACCOUNTING_AUDIT_ENABLED', true),
    ],
];
