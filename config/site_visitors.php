<?php

return [
    'enabled' => env('SITE_VISITORS_ENABLED', true),

    /*
    | Reject paths that start with any of these prefixes (admin / auth / app).
    */
    'blocked_path_prefixes' => [
        '/dashboard',
        '/login',
        '/register',
        '/logout',
        '/password',
        '/portal',
        '/media-library',
        '/visitors',
        '/visitor',
        '/use-analysis',
        '/order-intelligence',
        '/token-ledger',
        '/blog-ai',
        '/blog-posts',
        '/settings',
        '/users',
        '/roles',
        '/webhooks',
        '/logs',
        '/api/',
        '/sanctum',
        '/horizon',
        '/telescope',
        '/_ignition',
        '/analytics/visitors',
    ],

    'max_views_per_visitor_day' => (int) env('SITE_VISITORS_MAX_VIEWS_DAY', 80),
    'max_cta_per_visitor_hour' => (int) env('SITE_VISITORS_MAX_CTA_HOUR', 40),
    'max_tool_actions_per_visitor_hour' => (int) env('SITE_VISITORS_MAX_TOOL_HOUR', 60),

    /** Minimum seconds between persisted heartbeat rows per session+path. */
    'heartbeat_min_interval_seconds' => (int) env('SITE_VISITORS_HEARTBEAT_INTERVAL', 15),

    /** Client heartbeat interval hint (ms). */
    'client_heartbeat_interval_ms' => (int) env('SITE_VISITORS_CLIENT_HEARTBEAT_MS', 15000),

    'bot_ua_snippets' => [
        'bot',
        'spider',
        'crawl',
        'slurp',
        'facebookexternalhit',
        'bingpreview',
        'googlebot',
        'yandex',
        'baidu',
        'duckduckbot',
        'semrush',
        'ahrefs',
        'mj12bot',
        'petalbot',
        'bytespider',
    ],
];
