<?php

/**
 * Plugin subscription payment intent rules (subscribe / renew / upgrade / downgrade).
 *
 * Product rules:
 * - Active & not expired: change to a different plan (upgrade or downgrade); no renew on same plan.
 * - Expired or exhausted: renew current plan or change to a different plan.
 * - No active plan: subscribe (first assignment).
 * - Catalog ↔ legacy plan switches are not allowed via plugin payment.
 * - Plugin API blocks new payment requests while one is pending for the same domain.
 *
 * Enable SUBSCRIPTION_ENFORCE_PAYMENT_INTENT in production once the WordPress plugin
 * subscription modal is deployed. Enforcement applies to plugin API requests only by
 * default so merchant portal and admin manual payments keep legacy top-up behavior.
 */
return [
    'enforce_intent_rules' => (bool) env('SUBSCRIPTION_ENFORCE_PAYMENT_INTENT', false),

    'enforce_intent_plugin_api_only' => (bool) env('SUBSCRIPTION_ENFORCE_PAYMENT_INTENT_PLUGIN_ONLY', true),

    'validate_plugin_amounts' => (bool) env('SUBSCRIPTION_VALIDATE_PLUGIN_AMOUNTS', true),

    'intents' => [
        'subscribe',
        'renew',
        'upgrade',
        'downgrade',
    ],
];
