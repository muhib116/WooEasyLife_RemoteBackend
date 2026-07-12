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

    /*
    |--------------------------------------------------------------------------
    | Manual payment instructions (plugin API + landing pricing wizard)
    |--------------------------------------------------------------------------
    */
    'methods' => [
        [
            'payment_partner' => 'bKash',
            'account' => env('SUBSCRIPTION_PAYMENT_BKASH', '01770989591'),
            'note' => 'bKash "Send Money" ফি সাবস্ক্রিপশনের পরিমাণের সাথে যোগ হবে।',
            'steps' => [
                'bKash অ্যাপ খুলুন অথবা *247# ডায়াল করুন।',
                '"Send Money" বেছে নিন।',
                'নিচে দেখানো bKash নম্বরে টাকা পাঠান।',
                'প্ল্যানের মোট মূল্য (ফি সহ) লিখুন।',
                'bKash PIN দিয়ে নিশ্চিত করুন।',
                'Confirmation SMS থেকে Transaction ID কপি করে ফর্মে লিখুন।',
            ],
        ],
        [
            'payment_partner' => 'Rocket',
            'account' => env('SUBSCRIPTION_PAYMENT_ROCKET', '01770989591'),
            'note' => 'Rocket "Send Money" ফি সাবস্ক্রিপশনের পরিমাণের সাথে যোগ হবে।',
            'steps' => [
                'Rocket অ্যাপ খুলুন অথবা *322# ডায়াল করুন।',
                '"Send Money" বেছে নিন।',
                'নিচে দেখানো Rocket নম্বরে টাকা পাঠান।',
                'প্ল্যানের মোট মূল্য (ফি সহ) লিখুন।',
                'Rocket PIN দিয়ে নিশ্চিত করুন।',
                'Confirmation SMS থেকে Transaction ID কপি করে ফর্মে লিখুন।',
            ],
        ],
        [
            'payment_partner' => 'Nagad',
            'account' => env('LANDING_NAGAD_NUMBER', env('SUBSCRIPTION_PAYMENT_NAGAD', '01770989591')),
            'note' => 'Nagad "Send Money" ফি সাবস্ক্রিপশনের পরিমাণের সাথে যোগ হবে।',
            'steps' => [
                'Nagad অ্যাপ খুলুন অথবা *167# ডায়াল করুন।',
                '"Send Money" বেছে নিন।',
                'নিচে দেখানো Nagad নম্বরে টাকা পাঠান।',
                'প্ল্যানের মোট মূল্য (ফি সহ) লিখুন।',
                'Nagad PIN দিয়ে নিশ্চিত করুন।',
                'Confirmation SMS থেকে Transaction ID কপি করে ফর্মে লিখুন।',
            ],
        ],
    ],
];
