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
        'model' => 'gpt-4.1-mini',
        'min_confidence' => 95,
        'daily_auto_limit' => 30,
        'daily_review_limit' => 300,
        'background_review' => true,
    ],

    // Non-reasoning models only: the desk runs at temperature 0 with a strict schema.
    'models' => [
        'gpt-4.1-mini' => [
            'label' => 'GPT-4.1 mini',
            'hint' => 'Recommended — reads Bangla and Banglish notes well, low cost',
            'recommended' => true,
        ],
        'gpt-4o-mini' => [
            'label' => 'GPT-4o mini',
            'hint' => 'Lowest cost — more mistakes on mixed or long notes',
        ],
        'gpt-4.1' => [
            'label' => 'GPT-4.1',
            'hint' => 'Most accurate — about 5× the cost',
        ],
        'gpt-4o' => [
            'label' => 'GPT-4o',
            'hint' => 'Accurate — higher cost',
        ],
    ],

    'timeout' => 40,
    'judge_timeout' => 30,
    'max_tokens' => 1400,
    'judge_max_tokens' => 300,

    'max_context_chars' => 60000,
    'max_memory_items' => 30,
    'max_memory_when_chars' => 200,
    'max_memory_then_chars' => 400,

    'settings_cache_seconds' => 300,
];
