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
    | cancel / success_rate.
    |
    | RedX returns real delivery counts → include in totals.
    | Carrybee returns fraud-report counts only → never include in totals
    | (even if FRAUD_CHECK_AGGREGATE_CARRYBEE is flipped on by mistake,
    | CourierReportFormatter also skips data_type=fraud_reports).
    |
    */
    'aggregate_redx' => (bool) env('FRAUD_CHECK_AGGREGATE_REDX', true),
    'aggregate_carrybee' => false,
];
