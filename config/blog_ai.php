<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Blog AI (Bangladesh SEO writer)
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool) env('BLOG_AI_ENABLED', true),

    /*
    | When true, long OpenAI steps run on the queue (research/hooks/outline/draft/image).
    | Keep a queue worker running. Default false so local works without `queue:work`.
    | Set BLOG_AI_QUEUE=true in production with Supervisor/Forge workers.
    */
    'queue' => filter_var(env('BLOG_AI_QUEUE', false), FILTER_VALIDATE_BOOLEAN),

    'market' => 'Bangladesh',

    'default_locale' => 'bn',

    'author_name' => env('BLOG_AI_AUTHOR_NAME', 'Muhibbullah Ansary'),

    'author_role' => 'Developer of WooEasyLife',

    'daily_session_cap' => (int) env('BLOG_AI_DAILY_SESSION_CAP', 20),

    'daily_ai_calls_cap' => (int) env('BLOG_AI_DAILY_CALLS_CAP', 80),

    'daily_token_cap' => (int) env('BLOG_AI_DAILY_TOKEN_CAP', 400000),

    'require_pasted_keywords' => (bool) env('BLOG_AI_REQUIRE_KEYWORDS', true),

    'min_pasted_keywords' => 1,

    'min_body_words' => 800,

    'hooks_count' => 10,

    'internal_links_min' => 2,

    /*
    | Rough USD estimate for usage display (gpt-4o-mini ballpark).
    */
    'usd_per_1k_prompt_tokens' => 0.00015,
    'usd_per_1k_completion_tokens' => 0.0006,

    /*
    | Per-model chat rates (USD / 1K tokens). Falls back to defaults above.
    */
    'model_rates' => [
        'gpt-4o-mini' => ['prompt' => 0.00015, 'completion' => 0.0006],
        'gpt-4.1-mini' => ['prompt' => 0.0004, 'completion' => 0.0016],
        'gpt-4.1-nano' => ['prompt' => 0.0001, 'completion' => 0.0004],
        'gpt-4o' => ['prompt' => 0.0025, 'completion' => 0.01],
        'gpt-4.1' => ['prompt' => 0.002, 'completion' => 0.008],
        'o3-mini' => ['prompt' => 0.0011, 'completion' => 0.0044],
        'o4-mini' => ['prompt' => 0.0011, 'completion' => 0.0044],
    ],

    /*
    | Flat USD estimate per image generation call (approx).
    */
    'usd_per_image' => (float) env('BLOG_AI_USD_PER_IMAGE', 0.04),

    'busy_stale_minutes' => (int) env('BLOG_AI_BUSY_STALE_MINUTES', 5),

    'image_enabled' => (bool) env('BLOG_AI_IMAGE_ENABLED', true),

    'clusters' => [
        'fake_order' => 'ফেক অর্ডার / COD fraud',
        'fraud_checker' => 'ফ্রড চেকার / Courier history',
        'checkout_protection' => 'চেকআউট সুরক্ষা / OTP & block',
        'courier' => 'কুরিয়ার / অটো এন্ট্রি',
        'missing_order' => 'হারানো অর্ডার / Missing order',
        'facebook_ads' => 'Facebook Ads / Pixel',
        'ai_orders' => 'AI অর্ডার / Message & image to order',
        'packing_print' => 'প্যাকিং / Invoice & sticker',
        'multistore_app' => 'মাল্টিস্টোর / Mobile app',
        'team_calls' => 'টিম / Call tracking',
        'operations' => 'অপারেশন / ড্যাশবোর্ড',
        'general' => 'সাধারণ WooCommerce BD',
    ],

    /*
    | Seed queries for Google Suggest (gl=bd) when generating keywords per cluster.
    */
    'cluster_seed_queries' => [
        'fake_order' => ['ফেক অর্ডার', 'কিভাবে ফেক অর্ডার আটকাবো', 'COD fraud check'],
        'fraud_checker' => ['কুরিয়ার হিস্টোরি চেক', 'ফ্রড চেকার', 'pathao fraud check'],
        'checkout_protection' => ['চেকআউট ওটিপি', 'ডুপ্লিকেট অর্ডার ব্লক', 'fake customer block'],
        'courier' => ['কুরিয়ার অটো এন্ট্রি', 'pathao steadfast redx', 'WooCommerce courier'],
        'missing_order' => ['হারানো অর্ডার', 'missing order WooCommerce', 'abandoned checkout'],
        'facebook_ads' => ['Facebook pixel purchase', 'পিক্সেল প্রোটেকশন', 'Facebook ads COD'],
        'ai_orders' => ['মেসেজ থেকে অর্ডার', 'AI order WooCommerce', 'screenshot থেকে অর্ডার'],
        'packing_print' => ['ইনভয়েস প্রিন্ট', 'কুরিয়ার স্টিকার প্রিন্ট', 'packing slip'],
        'multistore_app' => ['মাল্টিস্টোর ড্যাশবোর্ড', 'WooCommerce mobile app', 'এক ড্যাশবোর্ডে সব স্টোর'],
        'team_calls' => ['কল হিস্ট্রি', 'customer call identifier', 'স্টাফ ম্যানেজমেন্ট'],
        'operations' => ['WooCommerce অপারেশন', 'অর্ডার ম্যানেজমেন্ট বাংলাদেশ', 'COD সেলার টুল'],
        'general' => ['WooCommerce বাংলাদেশ', 'COD ব্যবসা', 'অনলাইন ব্যবসা গাইড'],
    ],

    'static_internal_links' => [
        [
            'path' => '/',
            'title' => 'WooEasyLife হোম',
            'anchor_hints' => ['WooEasyLife', 'WooCommerce অপারেশন প্ল্যাটফর্ম'],
        ],
        [
            'path' => '/bd-fraud-checker',
            'title' => 'BD Fraud Checker',
            'anchor_hints' => ['ফ্রড চেকার', 'কুরিয়ার হিস্টোরি চেক'],
        ],
        [
            'path' => '/en/bd-fraud-checker',
            'title' => 'BD Fraud Checker (EN)',
            'anchor_hints' => ['fraud checker', 'courier history check'],
        ],
        [
            'path' => '/fake-order-protection',
            'title' => 'Fake Order Protection',
            'anchor_hints' => ['ফেক অর্ডার প্রোটেকশন', 'চেকআউট ওটিপি'],
        ],
        [
            'path' => '/courier-auto-entry',
            'title' => 'Courier Auto Entry',
            'anchor_hints' => ['কুরিয়ার অটো এন্ট্রি', 'Pathao Steadfast RedX'],
        ],
        [
            'path' => '/pricing',
            'title' => 'প্রাইসিং',
            'anchor_hints' => ['WooEasyLife প্রাইসিং', 'প্যাকেজ'],
        ],
    ],

    'persona' => [
        'product' => 'WooEasyLife',
        'audience' => 'বাংলাদেশের WooCommerce / Facebook / COD সেলার',
        'tone' => 'বাস্তবসম্মত, সহজ বাংলা, সেলার-টক — কর্পোরেট US ব্লগ নয়',
        'founder' => 'Muhibbullah Ansary, Developer of WooEasyLife',
    ],
];
