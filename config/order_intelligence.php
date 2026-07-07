<?php

return [
    'enabled' => (bool) env('ORDER_INTELLIGENCE_ENABLED', true),

    'fraud_check' => [
        // external_only | hybrid | platform_first
        'mode' => env('ORDER_INTELLIGENCE_FRAUD_CHECK_MODE', 'hybrid'),
        'min_platform_orders' => (int) env('ORDER_INTELLIGENCE_MIN_PLATFORM_ORDERS', 1),
        'max_stats_staleness_hours' => (int) env('ORDER_INTELLIGENCE_MAX_STATS_STALENESS_HOURS', 72),
        'max_snapshot_staleness_hours' => (int) env('ORDER_INTELLIGENCE_MAX_SNAPSHOT_STALENESS_HOURS', 24),
    ],

    'suggest' => [
        'enabled' => (bool) env('ORDER_INTELLIGENCE_SUGGEST_ENABLED', true),
        'min_query_length' => (int) env('ORDER_INTELLIGENCE_SUGGEST_MIN_LENGTH', 3),
        'default_limit' => (int) env('ORDER_INTELLIGENCE_SUGGEST_LIMIT', 8),
        'max_limit' => (int) env('ORDER_INTELLIGENCE_SUGGEST_MAX_LIMIT', 20),
    ],

    'search' => [
        'enabled' => (bool) env('ORDER_INTELLIGENCE_SEARCH_ENABLED', true),
        // database | meilisearch
        'driver' => env('ORDER_INTELLIGENCE_SEARCH_DRIVER', 'database'),
        'meilisearch' => [
            'host' => env('MEILISEARCH_HOST'),
            'key' => env('MEILISEARCH_KEY'),
            'index' => env('MEILISEARCH_CUSTOMER_INDEX', 'platform_customers'),
            'timeout' => (int) env('MEILISEARCH_TIMEOUT', 5),
        ],
    ],

    'cache' => [
        'enabled' => (bool) env('ORDER_INTELLIGENCE_CACHE_ENABLED', true),
        'ttl_seconds' => (int) env('ORDER_INTELLIGENCE_CACHE_TTL', 900),
        'prefix' => 'order_intel:',
    ],

    'statuses' => [
        'new_order',
        'courier_entry',
        'courier_handover',
        'delivered',
        'partially_delivered',
        'returned',
        'canceled',
    ],

    'terminal_statuses' => [
        'delivered',
        'partially_delivered',
        'returned',
        'canceled',
    ],

    'transitions' => [
        'new_order' => ['courier_entry', 'canceled'],
        'courier_entry' => ['courier_handover', 'canceled'],
        'courier_handover' => ['delivered', 'partially_delivered', 'returned'],
        'delivered' => [],
        'partially_delivered' => [],
        'returned' => [],
        'canceled' => [],
    ],

    'courier_status_map' => [
        'pathao' => [
            'order.created' => 'courier_entry',
            'order_created' => 'courier_entry',
            'order.picked' => 'courier_handover',
            'order_picked' => 'courier_handover',
            'order.in_transit' => 'courier_handover',
            'order_in_transit' => 'courier_handover',
            'order.delivered' => 'delivered',
            'order_delivered' => 'delivered',
            'order.partial_delivery' => 'partially_delivered',
            'order_partial_delivery' => 'partially_delivered',
            'order.returned' => 'returned',
            'order_returned' => 'returned',
            'order.cancelled' => 'canceled',
            'order_cancelled' => 'canceled',
        ],
        'steadfast' => [
            'pending' => 'courier_entry',
            'in_review' => 'courier_entry',
            'delivered_approval_pending' => 'courier_handover',
            'partial_delivered_approval_pending' => 'courier_handover',
            'cancelled_approval_pending' => 'canceled',
            'unknown_approval_pending' => 'courier_handover',
            'delivered' => 'delivered',
            'partial_delivered' => 'partially_delivered',
            'cancelled' => 'canceled',
            'hold' => 'courier_handover',
            'in_transit' => 'courier_handover',
            'returned' => 'returned',
        ],
        'redx' => [
            'pending' => 'courier_entry',
            'pickup' => 'courier_handover',
            'in_transit' => 'courier_handover',
            'delivered' => 'delivered',
            'partial_delivery' => 'partially_delivered',
            'returned' => 'returned',
            'cancelled' => 'canceled',
        ],
    ],

    'risk_tiers' => [
        'safe' => 0.75,
        'caution' => 0.45,
        'risky' => 0.0,
    ],

    'analytics' => [
        'enabled' => (bool) env('ORDER_INTELLIGENCE_ANALYTICS_ENABLED', true),
        'default_product_limit' => (int) env('ORDER_INTELLIGENCE_ANALYTICS_PRODUCT_LIMIT', 20),
    ],
];
