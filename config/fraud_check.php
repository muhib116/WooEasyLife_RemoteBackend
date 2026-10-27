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
    | A courier that is still missing goes to the waiting lane below. It is
    | asked again up to bulk_incomplete_retries times; after a failed try the
    | next one waits bulk_incomplete_retry_gap_seconds, doubling each time.
    | The admin Daily limits page can override these.
    |
    */
    'bulk_max_numbers' => 1000,
    'bulk_retry_attempts' => 3,
    'bulk_retry_pause_ms' => 300,
    'bulk_workers' => (int) env('FRAUD_CHECK_BULK_WORKERS', 3),
    'bulk_missing_courier_retries' => (int) env('FRAUD_CHECK_BULK_MISSING_COURIER_RETRIES', 1),
    'bulk_number_budget_seconds' => (int) env('FRAUD_CHECK_BULK_NUMBER_BUDGET_SECONDS', 60),
    'bulk_stale_check_seconds' => (int) env('FRAUD_CHECK_BULK_STALE_CHECK_SECONDS', 150),
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

    /*
    |--------------------------------------------------------------------------
    | Courier pace
    |--------------------------------------------------------------------------
    |
    | Calls are counted per courier account over window_seconds. A live order
    | check may use all per_window calls; bulk runs leave live_reserve of them
    | free and space their own calls bulk_gap_seconds apart (null spreads them
    | evenly over the window). A live check waits up to live_wait_seconds for
    | a slot; bulk never waits and leaves the number to the waiting lane.
    |
    | Pathao answers about 5 lookups a minute per account. Its 429 is honored
    | for at least rate_limit_rest_seconds, or longer when Retry-After says so.
    |
    | Paperfly blocks with HTTP 500 instead of 429. After block.after_errors
    | errors in a row it is left alone for block.pause_minutes, doubling up
    | to block.max_pause_minutes, and one lookup then checks whether it is back.
    | The admin Daily limits page can override the per-minute, reserve, gap,
    | and first pause numbers.
    |
    */
    'pace' => [
        'pathao' => [
            'per_window' => (int) env('FRAUD_CHECK_PATHAO_PER_MINUTE', 5),
            'window_seconds' => (int) env('FRAUD_CHECK_PATHAO_WINDOW_SECONDS', 62),
            'live_reserve' => (int) env('FRAUD_CHECK_PATHAO_LIVE_RESERVE', 1),
            'bulk_gap_seconds' => null,
            'live_wait_seconds' => 8,
            'bulk_wait_seconds' => 0,
            'rate_limit_rest_seconds' => (int) env('FRAUD_CHECK_PATHAO_RATE_LIMIT_REST_SECONDS', 65),
        ],
        'paperfly' => [
            'per_window' => 0,
            'window_seconds' => 60,
            'live_reserve' => 0,
            'bulk_gap_seconds' => (int) env('FRAUD_CHECK_PAPERFLY_GAP_SECONDS', 4),
            'live_wait_seconds' => 0,
            'bulk_wait_seconds' => 0,
            'block' => [
                'enabled' => true,
                'after_errors' => 2,
                'pause_minutes' => (int) env('FRAUD_CHECK_PAPERFLY_PAUSE_MINUTES', 5),
                'max_pause_minutes' => 30,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RedX fixed sample
    |--------------------------------------------------------------------------
    |
    | RedX currently answers every number with the same sample. After
    | after_samples of them in a row, RedX is not asked for skip_minutes.
    | 0 minutes keeps asking every time.
    |
    */
    'redx_sample' => [
        'after_samples' => 3,
        'skip_minutes' => (int) env('FRAUD_CHECK_REDX_SAMPLE_SKIP_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bulk waiting lane
    |--------------------------------------------------------------------------
    |
    | A bulk number whose SteadFast, Pathao, or Paperfly answer is still
    | missing shows "Waiting" instead of Incomplete. The scheduler asks only
    | that courier again, when the courier has room. A try is counted only
    | when the courier was really asked; after bulk_incomplete_retries tries,
    | or after max_minutes, the number is saved as Incomplete. One Pathao
    | account answers about 4 bulk numbers a minute, so 1000 numbers can
    | wait over four hours.
    |
    */
    'bulk_waiting' => [
        'max_minutes' => (int) env('FRAUD_CHECK_BULK_WAITING_MAX_MINUTES', 360),
    ],
];
