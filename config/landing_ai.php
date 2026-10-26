<?php

/*
|--------------------------------------------------------------------------
| Store landing page generator (WEL Elementor widget → Config → AI content)
|--------------------------------------------------------------------------
| Merchants never send an OpenAI key. The hub uses Settings → AI
| (landing.openai_api_key) and the landing model chosen there.
*/

return [
    'enabled' => (bool) env('LANDING_AI_ENABLED', true),

    // Empty = pick the best model from the live OpenAI list automatically.
    'model' => env('OPENAI_LANDING_MODEL'),

    // Finished or running generations per store license per day (failed ones do not count).
    'daily_limit' => (int) env('LANDING_AI_DAILY_LIMIT', 20),
    // Reasoning models only. Copywriting gains little from more effort, and low keeps a page under ~2 minutes.
    'reasoning_effort' => env('LANDING_AI_REASONING_EFFORT', 'low'),

    'models_cache_seconds' => 12 * 3600,
    'models_failure_cache_seconds' => 600,

    'max_reference_urls' => 3,
    'fetch_timeout' => 10,
    'fetch_connect_timeout' => 5,
    'max_redirects' => 3,
    'max_html_bytes' => 1_500_000,
    'max_page_chars' => 6000,

    // The merchant picks up to 4 photos from the WordPress Media Library.
    'max_images' => 4,
    'max_image_bytes' => 1_500_000,

    'max_context_chars' => 4000,
    'max_text_fields' => 160,
    'max_list_fields' => 20,

    // A generation older than this is cancelled and marked failed.
    'job_timeout_seconds' => 900,

    // Status polls inside this window reuse the last OpenAI answer.
    'poll_cache_seconds' => 2,
];
