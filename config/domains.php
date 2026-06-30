<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global domain uniqueness
    |--------------------------------------------------------------------------
    |
    | When enabled, a normalized store domain may only belong to one merchant
    | at a time across websites, plans, and license tokens.
    |
    */
    'enforce_global_uniqueness' => (bool) env('ENFORCE_GLOBAL_DOMAIN_UNIQUENESS', true),
];
