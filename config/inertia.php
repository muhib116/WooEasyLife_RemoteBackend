<?php

return [

    'ssr' => [
        /*
         * Keep OFF by default so production stays stable without a Node SSR process.
         * Enable with INERTIA_SSR_ENABLED=true and run: php artisan inertia:start-ssr
         * after: npm run build && npm run build:ssr
         */
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),

        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),

        'bundle' => base_path('bootstrap/ssr/ssr.js'),
    ],

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [
            resource_path('js/Pages'),
        ],
        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],

];
