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
    | Plugin free checks without a store Steadfast API key pair
    |--------------------------------------------------------------------------
    |
    | Merchants who are not domain-whitelisted and have not saved Steadfast
    | API key + secret key get this many free courier-history checks per
    | access token per day. The admin Daily limits page can override this.
    | After that the API asks the store to add its own Steadfast keys.
    |
    */
    'plugin_free_checks_without_steadfast' => (int) env('FRAUD_CHECK_PLUGIN_FREE_WITHOUT_STEADFAST', 10),

    /*
    |--------------------------------------------------------------------------
    | Merchant bulk check
    |--------------------------------------------------------------------------
    |
    | One live courier check per phone per Asia/Dhaka day. A failed check is
    | retried before it is saved, and a failed result is not reused.
    |
    */
    'bulk_max_numbers' => 1000,
    'bulk_retry_attempts' => 3,
    'bulk_retry_pause_ms' => 300,

    // A saved result where one courier did not answer is checked again
    // after this many seconds instead of being reused all day.
    'bulk_failed_courier_recheck_seconds' => (int) env('FRAUD_CHECK_BULK_FAILED_COURIER_RECHECK_SECONDS', 120),

    /*
    |--------------------------------------------------------------------------
    | Second attempt for a courier that did not answer
    |--------------------------------------------------------------------------
    |
    | A dropped connection or a courier server error is asked once more
    | before the answer goes out. When that second attempt also fails,
    | that courier is not retried again for cooldown_seconds, so an outage
    | does not slow every check. Rate limits, rejected credentials, and
    | missing setup are never retried right away. A bulk result where one
    | courier did not answer is checked again after
    | bulk_failed_courier_recheck_seconds.
    |
    */
    'courier_retry' => [
        'enabled' => (bool) env('FRAUD_CHECK_COURIER_RETRY', true),
        'pause_ms' => (int) env('FRAUD_CHECK_COURIER_RETRY_PAUSE_MS', 700),
        'budget_seconds' => (int) env('FRAUD_CHECK_COURIER_RETRY_BUDGET_SECONDS', 20),
        'cooldown_seconds' => (int) env('FRAUD_CHECK_COURIER_RETRY_COOLDOWN_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parallel courier calls for one phone
    |--------------------------------------------------------------------------
    |
    | Steadfast, Pathao, Paperfly, RedX, and Carrybee do not depend on each
    | other. When a saved session or API key is already available, those
    | lookups run together. A courier that still needs a login, or whose
    | pooled response is unusable, falls back to its existing check().
    |
    */
    'parallel_couriers' => (bool) env('FRAUD_CHECK_PARALLEL_COURIERS', true),
];
