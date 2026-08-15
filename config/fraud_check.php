<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Extra couriers on /api/fraud-check
    |--------------------------------------------------------------------------
    |
    | Steadfast, Pathao, and Paperfly are always included.
    | RedX / Carrybee can be toggled without a plugin release.
    |
    */
    'include_redx' => (bool) env('FRAUD_CHECK_INCLUDE_REDX', true),
    'include_carrybee' => (bool) env('FRAUD_CHECK_INCLUDE_CARRYBEE', true),

    /*
    |--------------------------------------------------------------------------
    | Aggregate totals
    |--------------------------------------------------------------------------
    |
    | Only count-based delivery data should affect total_order / confirmed /
    | cancel / success_rate. RedX and Carrybee both return real delivery
    | counts from their merchant customer APIs.
    |
    */
    'aggregate_redx' => (bool) env('FRAUD_CHECK_AGGREGATE_REDX', true),
    'aggregate_carrybee' => (bool) env('FRAUD_CHECK_AGGREGATE_CARRYBEE', true),

    /*
    |--------------------------------------------------------------------------
    | Plugin free checks without Steadfast portal credentials
    |--------------------------------------------------------------------------
    |
    | Merchants who are not domain-whitelisted and have not connected Steadfast
    | portal username/password get this many free courier-history checks per
    | access token per day (same idea as the public landing free limit).
    | After that the API returns an alert to connect Steadfast for full access.
    |
    */
    'plugin_free_checks_without_steadfast' => (int) env('FRAUD_CHECK_PLUGIN_FREE_WITHOUT_STEADFAST', 10),
];
