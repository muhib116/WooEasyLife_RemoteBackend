<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Knowledge candidate search
    |--------------------------------------------------------------------------
    |
    | database = SQL LIKE on match_text (default; CI / no Meili).
    | meilisearch = optional ID prefilter; Eloquent + scoreItem still own Evidence First.
    | inmemory = test-only in-process index.
    |
    */
    'knowledge_search' => [
        'driver' => env('WISE_KNOWLEDGE_SEARCH_DRIVER', 'database'),
        'meilisearch' => [
            'host' => env('MEILISEARCH_HOST'),
            'key' => env('MEILISEARCH_KEY'),
            'index' => env('WISE_KNOWLEDGE_INDEX', 'wise_knowledge_items'),
            'timeout' => (int) env('MEILISEARCH_TIMEOUT', 5),
        ],
    ],
];
