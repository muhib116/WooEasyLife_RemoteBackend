<?php

/**
 * Step 9 — Sunday weekly measurement for the active authority cluster.
 *
 * Mentor + `seo:weekly-report` read this. Trends only — no daily rank obsession.
 * Feed winners into next FAQ deepen; kill/merge 0-impression stubs after ~4 weeks.
 */

return [
    'cluster' => 'steadfast_fraud_check',
    'pillar_path' => '/steadfast-fraud-check',
    'compare_window_days' => 28,
    'command' => 'seo:weekly-report',

    /**
     * Primary URLs to pull from GSC page report / Visitors SEO tables.
     */
    'tracked_paths' => [
        '/steadfast-fraud-check',
        '/bd-fraud-checker',
        '/return-loss-calculator',
        '/fake-customer-check',
        '/faq',
        '/blog/steadfast-fraud-check-case-study',
        '/blog/steadfast-fraud-check-common-mistakes',
        '/blog/steadfast-fraud-check-faq',
        '/blog/steadfast-customer-history-ki',
        '/blog/steadfast-delivery-ratio-ki',
        '/blog/steadfast-return-komano',
        '/blog/kokhon-customer-verify-korbo',
        '/faq/phone-confirm-delivery-guarantee-ki',
        '/faq/history-na-thakle-ki-korbo',
        '/faq/wooeasylife-fraud-predict-kore-ki',
        '/faq/fake-order-chinhe-fela-jay-ki',
        '/faq/prottek-customer-verify-korbo-ki',
        '/faq/steadfast-return-request-kivabe',
        '/faq/steadfast-stuck-parcel-ki-korbo',
    ],

    /**
     * Query substrings (case-insensitive) that count as SteadFast-cluster demand.
     */
    'query_needles' => [
        'steadfast',
        'stead fast',
        'স্টিডফাস্ট',
        'fraud check',
        'ফ্রড চেক',
        'ফ্রড চেকার',
        'success rate',
        'সাকসেস রেট',
        'delivery ratio',
        'customer ratio',
        'customer history',
        'হিস্টোরি',
        'fake order',
        'ফেক অর্ডার',
        'fake customer',
        'return loss',
        'রিটার্ন',
    ],

    'metrics' => [
        [
            'key' => 'position',
            'label' => 'Avg position (pillar + head queries)',
            'goal' => 'Trend 10 → 8 → 6 → 4 (not daily swings)',
        ],
        [
            'key' => 'ctr',
            'label' => 'CTR',
            'goal' => 'Increasing week over week on pillar + tool',
        ],
        [
            'key' => 'impressions',
            'label' => 'Impressions',
            'goal' => 'Increasing every week on cluster queries/pages',
        ],
        [
            'key' => 'tool_clicks',
            'label' => 'Tool / pillar clicks',
            'goal' => 'Increasing on /bd-fraud-checker + /steadfast-fraud-check',
        ],
        [
            'key' => 'engagement',
            'label' => 'Engagement (time / scroll if available)',
            'goal' => 'Stable or up — not bounce-only traffic',
        ],
        [
            'key' => 'internal_links',
            'label' => 'Internal link clicks (GA events / Search Console pages)',
            'goal' => 'Cluster loop pages getting crawl + click share',
        ],
    ],

    /**
     * Sunday SOP for mentor "আজকের প্ল্যান" when weekday = sun.
     */
    'sunday_checklist' => [
        'Run `php artisan seo:weekly-report` (or open latest storage/app/seo/weekly-report-*.md)',
        'GSC → Performance → Pages: filter SteadFast cluster paths only',
        'GSC → Queries: steadfast / fraud check / success rate / হিস্টোরি — note top 5',
        'Record trend vs last Sunday (position, CTR, impressions, clicks) — not daily ranks',
        'Tool clicks: /bd-fraud-checker + /steadfast-fraud-check CTA',
        'Pick 1 winner query → deepen that FAQ/blog (stay in cluster)',
        'Flag any live cluster URL with ~0 impressions for 4+ weeks → improve or hold',
        'Do NOT start Pathao / Messenger / AI theme from this review',
        'Week 4 Sunday only: Step 10 gate — expand only if pillar tops + meaningful traffic',
    ],

    'actions' => [
        'winner' => 'Deepen matching FAQ or pillar section; soft-link from social to that URL',
        'striking_distance' => 'Title/meta CTR tweak (one change max) or FAQ answer expand',
        'zero_impression' => 'After ~4 weeks: improve, merge, or inventory status=hold — not more thin stubs',
        'losing' => 'Check internal links + honesty/SSR; do not theme-hop',
    ],

    'log_template' => [
        'date' => null,
        'pillar_clicks' => null,
        'pillar_impressions' => null,
        'pillar_ctr' => null,
        'pillar_position' => null,
        'tool_clicks' => null,
        'top_queries' => [],
        'winner_action' => null,
        'notes' => null,
    ],
];
