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
    | Functional Currency Defaults
    |--------------------------------------------------------------------------
    |
    | Default functional reporting currency. May be overridden per-tenant via
    | Ium::settings() using key 'ium.accounting.currency.functional'.
    |
    */
    'currency' => [
        'functional' => env('IUM_ACCOUNTING_FUNCTIONAL_CURRENCY', 'USD'),
        'symbol' => env('IUM_ACCOUNTING_CURRENCY_SYMBOL', '$'),
        'decimals' => (int) env('IUM_ACCOUNTING_CURRENCY_DECIMALS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Governance & Maker-Checker Segregation of Duties
    |--------------------------------------------------------------------------
    */
    'governance' => [
        'require_approval' => (bool) env('IUM_ACCOUNTING_REQUIRE_APPROVAL', true),
        'strict_maker_checker' => (bool) env('IUM_ACCOUNTING_MAKER_CHECKER_STRICT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default System Account Codes
    |--------------------------------------------------------------------------
    |
    | Default account codes resolved during automated workflows (year-end close,
    | realized/unrealized FX differences, fixed asset disposal).
    |
    */
    'accounts' => [
        'retained_earnings' => env('IUM_ACCOUNTING_RETAINED_EARNINGS_CODE', '3100'),
        'fx_realized_gain' => env('IUM_ACCOUNTING_FX_REALIZED_GAIN_CODE', '4910'),
        'fx_realized_loss' => env('IUM_ACCOUNTING_FX_REALIZED_LOSS_CODE', '5910'),
        'fx_unrealized_gain' => env('IUM_ACCOUNTING_FX_UNREALIZED_GAIN_CODE', '4920'),
        'fx_unrealized_loss' => env('IUM_ACCOUNTING_FX_UNREALIZED_LOSS_CODE', '5920'),
        'asset_disposal_gain' => env('IUM_ACCOUNTING_ASSET_DISPOSAL_GAIN_CODE', '4930'),
        'asset_disposal_loss' => env('IUM_ACCOUNTING_ASSET_DISPOSAL_LOSS_CODE', '5930'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Trail
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => (bool) env('IUM_ACCOUNTING_AUDIT_ENABLED', true),
    ],
];
