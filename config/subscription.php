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
        // Early SMS for plan/license expiry only (not quota). Days before expiry, plus 0 = today.
        'sms_expiry' => filter_var(env('SUBSCRIPTION_NOTIFY_SMS_EXPIRY', true), FILTER_VALIDATE_BOOLEAN),
        'sms_expiry_days' => array_values(array_unique(array_map(
            'intval',
            array_filter(
                explode(',', (string) env('SUBSCRIPTION_NOTIFY_SMS_EXPIRY_DAYS', '7,3,1,0')),
                fn (string $day) => $day !== ''
            )
        ))),
        'sms_support_phone' => env('SUBSCRIPTION_SMS_SUPPORT_PHONE', '01770989591'),
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
