<?php

/*
|--------------------------------------------------------------------------
| Return desk AI (WEL plugin → Return Requests → SteadFast hold parcels)
|--------------------------------------------------------------------------
| The store builds a masked case file; the hub owns the engine (flags, promise
| dates, guards, judge), the prompt, model, OpenAI key (Settings → AI), each
| store's memory rules and every limit.
| Settings → AI → Return desk saves overrides for the defaults below.
*/

return [
    'enabled' => (bool) env('RETURN_AI_ENABLED', true),

    'defaults' => [
        'mode' => 'suggest',
        'auto_note' => false,
        'auto_resend' => false,
        'auto_cancel' => false,
        // "auto" = the strongest text model on the hub key (Settings → AI list).
        'model' => 'auto',
        'min_confidence' => 95,
        'daily_auto_limit' => 30,
        'daily_review_limit' => 300,
        'background_review' => true,
    ],

    // The dropdown is the live OpenAI text-model list (same filter as landing AI).
    // Reasoning models skip temperature; the client sends max_completion_tokens.
    'reasoning_effort' => 'medium',
    'judge_reasoning_effort' => 'low',

    'timeout' => 70,
    'judge_timeout' => 40,
    'max_tokens' => 2200,
    'judge_max_tokens' => 400,

    'max_context_chars' => 60000,
    'max_memory_items' => 30,
    'max_memory_when_chars' => 200,
    'max_memory_then_chars' => 400,

    'settings_cache_seconds' => 300,
];
