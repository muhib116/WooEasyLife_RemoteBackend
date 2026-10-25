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
