<?php

return [
    'bulk_status_concurrency' => (int) env('COURIER_BULK_STATUS_CONCURRENCY', 15),

    /*
     * When false, a Steadfast X-Signature mismatch is logged and the request is
     * still accepted if the Bearer token matches. Turn on only after Steadfast
     * delivery health shows a 200 well under 5 seconds and steadfast_signature_mismatch
     * logs are quiet for real traffic.
     */
    'steadfast_enforce_webhook_signature' => filter_var(
        env('STEADFAST_ENFORCE_WEBHOOK_SIGNATURE', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];
