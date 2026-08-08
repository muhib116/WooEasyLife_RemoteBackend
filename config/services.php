<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'bulksms' => [
        'api_key' => env('BULKSMS_API_KEY'),
        'sender_id' => env('BULKSMS_SENDER_ID', '8809617619992'),
        'checkout_otp_max_per_phone_hour' => (int) env('SMS_CHECKOUT_OTP_MAX_PER_PHONE_HOUR', 8),
        'checkout_otp_max_per_merchant_hour' => (int) env('SMS_CHECKOUT_OTP_MAX_PER_MERCHANT_HOUR', 120),
    ],

    'plugin_upload' => [
        'api_key' => env('PLUGIN_UPLOAD_API_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/marchent/auth/google/callback'),
    ],

        'facebook' => [
            'client_id' => env('FACEBOOK_CLIENT_ID'),
            'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
            'redirect' => env('FACEBOOK_REDIRECT_URI', env('APP_URL').'/marchent/auth/facebook/callback'),

            /*
            | Page Graph API (admin blog → Facebook Page share).
            | Use a long-lived Page token from GET /me/accounts (not a User token).
            */
            'page_id' => env('FACEBOOK_PAGE_ID'),
            'page_access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
            'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),
            // Optional public site URL used in Facebook captions/links (needed when APP_URL is localhost).
            'share_base_url' => env('FACEBOOK_SHARE_BASE_URL'),
        ],

        /*
        | Messenger Page OAuth for Woo Easy Life plugin one-click connect.
        | Reuses FACEBOOK_CLIENT_ID/SECRET unless META_APP_* overrides are set.
        */
        'messenger' => [
            'app_id' => env('META_APP_ID', env('FACEBOOK_CLIENT_ID')),
            'app_secret' => env('META_APP_SECRET', env('FACEBOOK_CLIENT_SECRET')),
            /*
            | Messenger MUST use its own callback (/api/messenger/oauth/callback).
            | Do NOT share FACEBOOK_REDIRECT_URI — that is the merchant-login callback
            | and Facebook would never return to the Messenger OAuth handler.
            */
            'redirect' => env(
                'META_MESSENGER_REDIRECT_URI',
                env('APP_URL').'/api/messenger/oauth/callback'
            ),
            'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),
            'webhook_verify_token' => env('META_MESSENGER_WEBHOOK_VERIFY_TOKEN'),
            // Messenger inbox needs list + messaging + webhook subscribe.
            // pages_read_engagement is NOT required and is often Invalid Scopes on Messenger apps.
            'scopes' => env(
                'META_MESSENGER_SCOPES',
                'pages_show_list,pages_messaging,pages_manage_metadata,pages_read_engagement,pages_manage_engagement,pages_read_user_content,instagram_basic,instagram_manage_messages'
            ),
            /*
            | Facebook Login for Business configuration ID (preferred).
            | When set, OAuth uses config_id instead of a raw scope= list — required for
            | newer Meta apps where pages_* permissions are tied to a use-case config.
            | Create under: App Dashboard → Facebook Login for Business → Configurations.
            */
            'login_config_id' => env('META_MESSENGER_LOGIN_CONFIG_ID'),

            /*
            | Direct connect (single-page mode): skip the Facebook login dialog and
            | attach an already-issued Page token. Intended for development and for
            | self-hosted hubs that only serve one Page, since every license that
            | connects would share this Page. Keep disabled for multi-tenant SaaS.
            */
            'direct_connect' => filter_var(
                env('META_MESSENGER_DIRECT_CONNECT', false),
                FILTER_VALIDATE_BOOLEAN
            ),
            'page_id' => env('META_MESSENGER_PAGE_ID', env('FACEBOOK_PAGE_ID')),
            'page_access_token' => env('META_MESSENGER_PAGE_ACCESS_TOKEN', env('FACEBOOK_PAGE_ACCESS_TOKEN')),
        ],

        /*
        | Public SEO courier-charge calculator rate sync.
        | Steadfast uses a public pricing API (no credentials).
        | Pathao merchant price-plan requires a CourierConfiguration for pathao_user_id.
        */
        'courier_public_rates' => [
            'pathao_user_id' => (int) env('COURIER_PUBLIC_RATES_PATHAO_USER_ID', 0),
            /*
            | Sample recipient city/zone IDs from Pathao merchant catalog (per account).
            | weight_kg ≈ base slab; heavy_weight_kg used to estimate per-kg extra.
            */
            'pathao_samples' => [
                'dhaka' => [
                    'recipient_city' => (int) env('COURIER_PUBLIC_RATES_PATHAO_DHAKA_CITY', 0),
                    'recipient_zone' => (int) env('COURIER_PUBLIC_RATES_PATHAO_DHAKA_ZONE', 0),
                    'weight_kg' => 0.5,
                    'heavy_weight_kg' => 2,
                ],
                'suburb' => [
                    'recipient_city' => (int) env('COURIER_PUBLIC_RATES_PATHAO_SUBURB_CITY', 0),
                    'recipient_zone' => (int) env('COURIER_PUBLIC_RATES_PATHAO_SUBURB_ZONE', 0),
                    'weight_kg' => 0.5,
                    'heavy_weight_kg' => 2,
                ],
                'outside' => [
                    'recipient_city' => (int) env('COURIER_PUBLIC_RATES_PATHAO_OUTSIDE_CITY', 0),
                    'recipient_zone' => (int) env('COURIER_PUBLIC_RATES_PATHAO_OUTSIDE_ZONE', 0),
                    'weight_kg' => 0.5,
                    'heavy_weight_kg' => 2,
                ],
            ],
        ],

    ];
