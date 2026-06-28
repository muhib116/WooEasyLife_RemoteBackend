<?php

return [
    'quota_low_threshold' => (int) env('SUBSCRIPTION_QUOTA_LOW', 20),
    'quota_critical_threshold' => (int) env('SUBSCRIPTION_QUOTA_CRITICAL', 5),
    'subscription_expiring_days' => (int) env('SUBSCRIPTION_EXPIRING_DAYS', 7),
    'license_expiring_days' => (int) env('LICENSE_EXPIRING_DAYS', 7),
    'notice_cache_hours' => (int) env('SUBSCRIPTION_NOTICE_CACHE_HOURS', 2),

    'notifications' => [
        'enabled' => (bool) env('SUBSCRIPTION_NOTIFICATIONS_ENABLED', true),
        'min_severity' => env('SUBSCRIPTION_NOTIFY_MIN_SEVERITY', 'warning'),
        'email' => (bool) env('SUBSCRIPTION_NOTIFY_EMAIL', true),
        'sms' => (bool) env('SUBSCRIPTION_NOTIFY_SMS', false),
        'whatsapp' => (bool) env('SUBSCRIPTION_NOTIFY_WHATSAPP', false),
        // Required when whatsapp is true — POST endpoint receiving phone, message, domain, etc.
        'whatsapp_webhook_url' => env('SUBSCRIPTION_WHATSAPP_WEBHOOK_URL'),
        'portal_url' => env('SUBSCRIPTION_PORTAL_URL', env('APP_URL', 'http://localhost')),
    ],

    'expiry' => [
        'auto_disable_tokens' => (bool) env('SUBSCRIPTION_AUTO_DISABLE_TOKENS', true),
        'auto_deactivate_plans' => (bool) env('SUBSCRIPTION_AUTO_DEACTIVATE_PLANS', true),
    ],
];
