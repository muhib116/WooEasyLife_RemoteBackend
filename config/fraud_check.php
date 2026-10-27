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
    | bulk_workers numbers of one merchant are checked at the same time.
    | When SteadFast, Pathao, or Paperfly did not answer, only that courier
    | is asked again (bulk_missing_courier_retries times). No new attempt
    | starts after bulk_number_budget_seconds. A number still "checking"
    | after bulk_stale_check_seconds is handed to the next request.
    | A saved Incomplete row is asked again up to bulk_incomplete_retries
    | times, bulk_incomplete_retry_gap_seconds apart, without holding a
    | worker slot. The admin Daily limits page can override these.
    |
    */
    'bulk_max_numbers' => 1000,
    'bulk_retry_attempts' => 3,
    'bulk_retry_pause_ms' => 300,
    'bulk_workers' => (int) env('FRAUD_CHECK_BULK_WORKERS', 3),
    'bulk_missing_courier_retries' => (int) env('FRAUD_CHECK_BULK_MISSING_COURIER_RETRIES', 1),
    'bulk_number_budget_seconds' => (int) env('FRAUD_CHECK_BULK_NUMBER_BUDGET_SECONDS', 60),
    'bulk_stale_check_seconds' => (int) env('FRAUD_CHECK_BULK_STALE_CHECK_SECONDS', 150),
    // Extra live checks for a row that stayed Incomplete. These run one at a
    // time, after the gap, and do not take a bulk worker slot.
    'bulk_incomplete_retries' => (int) env('FRAUD_CHECK_BULK_INCOMPLETE_RETRIES', 5),
    'bulk_incomplete_retry_gap_seconds' => (int) env('FRAUD_CHECK_BULK_INCOMPLETE_RETRY_GAP_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Second attempt for a courier that did not answer
    |--------------------------------------------------------------------------
    |
    | A dropped connection or a courier server error is asked once more
    | before the answer goes out. When that second attempt also fails, the
    | same number is not retried on that courier for cooldown_seconds.
    | After pause_after_failures failed retries in a row (any numbers), the
    | courier is not retried at all for cooldown_seconds, so an outage does
    | not slow every check. Rate limits, rejected credentials, and missing
    | setup are never retried right away.
    |
    */
    'courier_retry' => [
        'enabled' => (bool) env('FRAUD_CHECK_COURIER_RETRY', true),
        'pause_ms' => (int) env('FRAUD_CHECK_COURIER_RETRY_PAUSE_MS', 700),
        'budget_seconds' => (int) env('FRAUD_CHECK_COURIER_RETRY_BUDGET_SECONDS', 20),
        'cooldown_seconds' => (int) env('FRAUD_CHECK_COURIER_RETRY_COOLDOWN_SECONDS', 60),
        'pause_after_failures' => (int) env('FRAUD_CHECK_COURIER_RETRY_PAUSE_AFTER_FAILURES', 3),
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
