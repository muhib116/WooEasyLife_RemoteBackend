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

    /*
    |--------------------------------------------------------------------------
    | Grounded conversational assist (Wave 1+)
    |--------------------------------------------------------------------------
    |
    | Logical 15-layer pipeline; typically 1 structured OpenAI call (+ retries).
    | Strong knowledge hits skip assist. Hard FAQ publish stays human-gated.
    |
    */
    'grounded_assist' => [
        'max_attempts' => (int) env('WISE_ASSIST_MAX_ATTEMPTS', 3),
        'min_score' => (float) env('WISE_ASSIST_MIN_SCORE', 9.0),
        'min_confidence' => (int) env('WISE_ASSIST_MIN_CONFIDENCE', 95),
        'recent_turns' => (int) env('WISE_ASSIST_RECENT_TURNS', 12),
        'max_chunks' => (int) env('WISE_ASSIST_MAX_CHUNKS', 8),
        'max_chunk_chars' => (int) env('WISE_ASSIST_MAX_CHUNK_CHARS', 400),
        'prompt_version' => (string) env('WISE_ASSIST_PROMPT_VERSION', 'grounded-assist-v1'),
        'timeout_seconds' => (int) env('WISE_ASSIST_TIMEOUT', 45),
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft conversation memory (Wave 2)
    |--------------------------------------------------------------------------
    */
    'conversation_memory' => [
        'summary_max_chars' => (int) env('WISE_CONV_SUMMARY_MAX', 800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Continuous learning (Wave 3) — draft only, never auto-publish
    |--------------------------------------------------------------------------
    */
    'continuous_learning' => [
        'enabled' => filter_var(env('WISE_CL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'min_assist_score' => (float) env('WISE_CL_MIN_SCORE', 9.0),
    ],
];
