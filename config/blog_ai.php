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

    /*
    | Soft publish length gate / AI draft target floor.
    | Prompts aim slightly higher (1400–2000) so drafts clear this bar.
    | Ops: after ~1 week of stable drafts, consider raising to 1400.
    */
    'min_body_words' => (int) env('BLOG_AI_MIN_BODY_WORDS', 1200),

    /*
    | Glossary article_type uses a lower floor so short definition posts are not forced long.
    */
    'glossary_min_body_words' => (int) env('BLOG_AI_GLOSSARY_MIN_BODY_WORDS', 800),

    'article_types' => [
        'howto',
        'comparison',
        'glossary',
        'case_study',
    ],

    'default_article_type' => env('BLOG_AI_DEFAULT_ARTICLE_TYPE', 'howto'),

    /*
    | Pivot blog primaries that collide with money landing head terms (never fail research).
    */
    'lp_keyword_guard' => filter_var(env('BLOG_AI_LP_KEYWORD_GUARD', true), FILTER_VALIDATE_BOOLEAN),

    'hooks_count' => 10,

    'internal_links_min' => 3,

    /*
    | On-page SEO quality gates for AI drafts + soft publish warnings.
    | Publish stays gentle: duplicate focus keyword is the only hard SEO block.
    */
    'seo_quality' => [
        'min_internal_links' => 2,
        'min_faqs' => 5,
        /*
        | Extra AI gates for RankMath-style completeness (draft + readiness score).
        */
        'require_keyword_in_h2' => true,
        'require_quick_answer' => true,
        'require_ai_search_summary' => true,
        'require_h3' => true,
        // Prefer natural paragraphs; lists only when counting real steps (prompt-enforced).
        'require_lists' => filter_var(env('BLOG_SEO_REQUIRE_LISTS', false), FILTER_VALIDATE_BOOLEAN),
        /*
        | Hard publish gates only — everything else is soft-warned in the CMS.
        */
        'enforce_on_publish' => [
            /*
            | Progressive publish gates (drafts never blocked).
            | C1: focus keyword on (default). C2: enable INTERNAL_LINK after C1 is stable.
            | Do not enable AI_READY or OG_IMAGE until Fix-checklist path is routine.
            */
            'ai_ready' => filter_var(env('BLOG_SEO_ENFORCE_AI_READY', false), FILTER_VALIDATE_BOOLEAN),
            'focus_keyword_required' => filter_var(env('BLOG_SEO_ENFORCE_FOCUS_KEYWORD', true), FILTER_VALIDATE_BOOLEAN),
            'has_og_or_content_image' => filter_var(env('BLOG_SEO_ENFORCE_OG_IMAGE', false), FILTER_VALIDATE_BOOLEAN),
            'has_internal_link' => filter_var(env('BLOG_SEO_ENFORCE_INTERNAL_LINK', false), FILTER_VALIDATE_BOOLEAN),
            'duplicate_focus_keyword' => true,
            'duplicate_slug' => false, // DB unique rule already covers slug
            /*
            | Soft-pass Auto drafts cannot publish until Fix SEO checklist clears the flag.
            */
            'block_soft_pass' => filter_var(env('BLOG_SEO_BLOCK_SOFT_PASS_PUBLISH', true), FILTER_VALIDATE_BOOLEAN),
        ],
        /*
        | Soft warnings shown in the CMS (confirm dialogs / checklist) — do not block save.
        */
        'soft_warn_on_publish' => [
            'keyword_in_title' => true,
            'keyword_in_first_paragraph' => true,
            'word_count_ok' => true,
            'missing_og_image' => true,
            'missing_content_image' => true,
            'ai_ready' => true,
        ],
    ],

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
        'gpt-5' => ['prompt' => 0.00125, 'completion' => 0.01],
        'gpt-5-mini' => ['prompt' => 0.00025, 'completion' => 0.002],
        'gpt-5-nano' => ['prompt' => 0.00005, 'completion' => 0.0004],
        'o3' => ['prompt' => 0.002, 'completion' => 0.008],
        'o3-mini' => ['prompt' => 0.0011, 'completion' => 0.0044],
        'o4-mini' => ['prompt' => 0.0011, 'completion' => 0.0044],
    ],

    /*
    | Flat USD estimate per image generation call (approx).
    */
    'usd_per_image' => (float) env('BLOG_AI_USD_PER_IMAGE', 0.04),

    'busy_stale_minutes' => (int) env('BLOG_AI_BUSY_STALE_MINUTES', 12),

    'image_enabled' => (bool) env('BLOG_AI_IMAGE_ENABLED', true),

    /*
    | Cover banner generation (gpt-image edits + vision review gate).
    | author_reference is required for identity lock; style refs are optional.
    |
    | IMPORTANT: gpt-image models garbles Bangla (Indic shaping). Covers use
    | Latin/English marketing lines only. Post title stays Bangla in the CMS.
    */
    'image' => [
        'author_reference' => resource_path('blog-ai/author-reference.png'),
        /*
        | Style refs help layout/lighting but often drift facial likeness.
        | Default: author-only identity (best face match). Set true to blend styles.
        */
        'use_style_references' => filter_var(env('BLOG_AI_IMAGE_STYLE_REFS', false), FILTER_VALIDATE_BOOLEAN),
        'style_references' => [
            resource_path('blog-ai/style-reference-1.png'),
            resource_path('blog-ai/style-reference-2.png'),
            resource_path('blog-ai/style-reference-3.png'),
            resource_path('blog-ai/style-reference-4.png'),
        ],
        'max_style_references' => (int) env('BLOG_AI_IMAGE_MAX_STYLE_REFS', 1),
        'max_generate_attempts' => (int) env('BLOG_AI_IMAGE_MAX_ATTEMPTS', 3),
        'review_pass_score' => (int) env('BLOG_AI_IMAGE_REVIEW_SCORE', 72),
        'size' => env('BLOG_AI_IMAGE_SIZE', '1536x1024'),
        'input_fidelity' => env('BLOG_AI_IMAGE_INPUT_FIDELITY', 'high'),
        /*
        | Prefer clear Latin marketing copy on the banner (AI typography).
        | true = no Bengali script glyphs on generated covers.
        */
        'latin_cover_text_only' => filter_var(env('BLOG_AI_IMAGE_LATIN_TEXT_ONLY', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    | First-party blog analytics → learning insights for smarter drafting.
    */
    'analytics' => [
        'enabled' => (bool) env('BLOG_ANALYTICS_ENABLED', true),
        'learning_in_prompts' => (bool) env('BLOG_LEARNING_IN_PROMPTS', true),
        'max_views_per_visitor_day' => (int) env('BLOG_ANALYTICS_MAX_VIEWS_PER_VISITOR_DAY', 40),
        'max_cta_per_visitor_hour' => (int) env('BLOG_ANALYTICS_MAX_CTA_PER_VISITOR_HOUR', 20),
        'spam_views_per_slug_day_cap' => (int) env('BLOG_ANALYTICS_SPAM_VIEWS_SLUG_DAY_CAP', 80),
    ],

    /*
    | Landing page reference — cluster primary_path URL + optional live snapshot
    | used as content source of truth for Blog AI (structure still uses skeletons).
    */
    'landing_reference' => [
        'fetch_live' => filter_var(env('BLOG_LANDING_REF_FETCH', true), FILTER_VALIDATE_BOOLEAN),
        'public_base_url' => env('BLOG_LANDING_PUBLIC_BASE_URL', env('APP_URL')),
        'fetch_timeout' => (int) env('BLOG_LANDING_REF_TIMEOUT', 8),
        'max_html_bytes' => (int) env('BLOG_LANDING_REF_MAX_HTML', 400000),
        'allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('BLOG_LANDING_REF_ALLOWED_HOSTS', ''))
        ))),
    ],

    /*
    | Competitor URL analyzer — discover / fetch public pages + LLM gap analysis for drafting.
    */
    'competitors' => [
        'enabled' => filter_var(env('BLOG_COMPETITOR_ANALYZER', true), FILTER_VALIDATE_BOOLEAN),
        'in_prompts' => filter_var(env('BLOG_COMPETITOR_IN_PROMPTS', true), FILTER_VALIDATE_BOOLEAN),
        'max_urls' => (int) env('BLOG_COMPETITOR_MAX_URLS', 5),
        'max_age_days' => (int) env('BLOG_COMPETITOR_MAX_AGE_DAYS', 30),
        'fetch_timeout' => (int) env('BLOG_COMPETITOR_FETCH_TIMEOUT', 12),
        'max_html_bytes' => (int) env('BLOG_COMPETITOR_MAX_HTML_BYTES', 500000),
        'excerpt_chars' => (int) env('BLOG_COMPETITOR_EXCERPT_CHARS', 3500),
        'discovery' => [
            'enabled' => filter_var(env('BLOG_COMPETITOR_DISCOVERY', true), FILTER_VALIDATE_BOOLEAN),
            /*
            | auto = Brave when BLOG_COMPETITOR_SEARCH_API_KEY is set, else DuckDuckGo HTML.
            */
            'provider' => env('BLOG_COMPETITOR_SEARCH_PROVIDER', 'auto'),
            'api_key' => env('BLOG_COMPETITOR_SEARCH_API_KEY'),
            'bing_api_key' => env('BLOG_COMPETITOR_BING_API_KEY'),
            'max_results' => (int) env('BLOG_COMPETITOR_DISCOVERY_MAX', 5),
            'auto_on_smart_post' => filter_var(env('BLOG_COMPETITOR_AUTO_ON_SMART', true), FILTER_VALIDATE_BOOLEAN),
            'exclude_hosts' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env(
                    'BLOG_COMPETITOR_EXCLUDE_HOSTS',
                    'wpsalehub.com,www.wpsalehub.com,app.wpsalehub.com,wooeasylife.com,www.wooeasylife.com'
                ))
            ))),
        ],
    ],

    /*
    | Standing memory — keywords, topics, instructions that compound day by day.
    */
    'memory' => [
        'enabled' => filter_var(env('BLOG_MEMORY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'in_prompts' => filter_var(env('BLOG_MEMORY_IN_PROMPTS', true), FILTER_VALIDATE_BOOLEAN),
        'auto_absorb_learning' => filter_var(env('BLOG_MEMORY_ABSORB_LEARNING', true), FILTER_VALIDATE_BOOLEAN),
        'auto_absorb_competitor' => filter_var(env('BLOG_MEMORY_ABSORB_COMPETITOR', true), FILTER_VALIDATE_BOOLEAN),
        'prompt_limit' => (int) env('BLOG_MEMORY_PROMPT_LIMIT', 40),
    ],

    'clusters' => [
        'fake_order' => 'ফেক অর্ডার / COD fraud',
        'fraud_checker' => 'ফ্রড চেকার / Courier history',
        'return_loss' => 'রিটার্ন লস ক্যালকুলেটর',
        'checkout_protection' => 'চেকআউট সুরক্ষা / OTP & block',
        'courier' => 'কুরিয়ার / অটো এন্ট্রি + SteadFast হাব',
        'courier_charge' => 'কুরিয়ার চার্জ ক্যালকুলেটর',
        'messenger' => 'Facebook Messenger ইনবক্স',
        'missing_order' => 'হারানো অর্ডার / Missing order',
        'facebook_ads' => 'Facebook Ads / Pixel / ROAS',
        'ai_orders' => 'AI অর্ডার / Message & image to order',
        'packing_print' => 'প্যাকিং / Invoice & sticker',
        'multistore_app' => 'মাল্টিস্টোর / Mobile app',
        'team_calls' => 'টিম / Call tracking',
        'operations' => 'অপারেশন / ড্যাশবোর্ড',
        'woocommerce' => 'WooCommerce Bangladesh',
        'cod' => 'COD / Cash on Delivery',
        'general' => 'সাধারণ WooCommerce BD',
    ],

    /*
    | Free SEO tools to rank via blog internal links (high-intent keywords).
    | Agents must prefer these paths with keyword-rich anchors.
    */
    'seo_tools' => [
        [
            'path' => '/bd-fraud-checker',
            'title' => 'ফ্রি ফ্রড চেকার',
            'priority' => 100,
            'keywords' => ['ফ্রড চেকার', 'কুরিয়ার হিস্টোরি চেক', 'courier fraud checker bd', 'pathao steadfast redx চেক'],
        ],
        [
            'path' => '/return-loss-calculator',
            'title' => 'রিটার্ন লস ক্যালকুলেটর',
            'priority' => 95,
            'keywords' => ['রিটার্ন লস ক্যালকুলেটর', 'COD রিটার্ন লস', 'ফেক অর্ডার খরচ হিসাব', 'মাসিক রিটার্ন লস'],
        ],
        [
            'path' => '/courier-charge-calculator',
            'title' => 'কুরিয়ার চার্জ ক্যালকুলেটর',
            'priority' => 90,
            'keywords' => ['কুরিয়ার চার্জ ক্যালকুলেটর', 'Pathao চার্জ', 'Steadfast চার্জ', 'RedX ডেলিভারি চার্জ'],
        ],
        [
            'path' => '/ads-roas-calculator',
            'title' => 'Facebook Ads ROAS ক্যালকুলেটর',
            'priority' => 90,
            'keywords' => ['Facebook Ads ROAS', 'ফেক Purchase', 'পিক্সেল ROAS', 'অ্যাড বাজেট নষ্ট'],
        ],
        [
            'path' => '/fake-order-protection',
            'title' => 'ফেক অর্ডার প্রোটেকশন',
            'priority' => 85,
            'keywords' => ['ফেক অর্ডার প্রোটেকশন', 'কিভাবে ফেক অর্ডার আটকাবো', 'চেকআউট OTP'],
        ],
        [
            'path' => '/courier-auto-entry',
            'title' => 'কুরিয়ার অটো এন্ট্রি',
            'priority' => 80,
            'keywords' => ['কুরিয়ার অটো এন্ট্রি', 'Pathao Steadfast RedX অটো', 'পার্সেল অটো এন্ট্রি', 'পার্সেল নোট হিস্ট্রি', 'steadfast parcel note'],
        ],
        [
            'path' => '/steadfast-integration',
            'title' => 'Steadfast Integration',
            'priority' => 78,
            'keywords' => [
                'steadfast woocommerce plugin',
                'steadfast return request',
                'steadfast ask to return',
                'stuck parcel steadfast',
                'steadfast portal notifications',
                'SteadFast ইন্টিগ্রেশন',
            ],
        ],
        [
            'path' => '/steadfast-return-hub',
            'title' => 'SteadFast Return Hub',
            'priority' => 82,
            'keywords' => [
                'steadfast return request',
                'steadfast ask to return',
                'stuck parcel',
                'steadfast notifications',
                'রিটার্ন রিকোয়েস্ট',
            ],
        ],
        [
            'path' => '/woocommerce-facebook-messenger',
            'title' => 'Facebook Messenger ইনবক্স',
            'priority' => 76,
            'keywords' => [
                'woocommerce facebook messenger',
                'messenger inbox',
                'facebook page chat wordpress',
                'মেসেঞ্জার ইনবক্স',
            ],
        ],
        [
            'path' => '/pricing',
            'title' => 'প্রাইসিং / ট্রায়াল',
            'priority' => 70,
            'keywords' => ['WooEasyLife প্রাইসিং', 'ফ্রি ট্রায়াল', 'messenger inbox plugin'],
        ],
    ],

    /*
    | Shipped product truth for Blog AI (keep in sync with FEATURES.md).
    | Injected into every product_brief — writers must not invent beyond this.
    */
    'product_truth' => [
        'version' => 'plugin-1.5.4',
        'hero_claims' => [
            'BD fraud / courier history check before confirm',
            'Fake-order protection: OTP, duplicate block, blacklist',
            'Courier auto-entry + webhooks: Pathao, SteadFast, RedX',
            'SteadFast Courier hub: Return Requests (Ask to return → Decide cancel/resend) + Notifications + stuck-parcel scan (courier_automation; portal login)',
            'Facebook Messenger inbox in WP admin: Page connect, chat, media/voice, lead labels (NURTURE etc.)',
            'Missing order recovery, AI order from image/text, mobile app, SMS, Meta Pixel/CAPI',
        ],
        'courier_hub' => [
            'partners_with_hub' => ['steadfast'],
            'return_statuses' => ['pending', 'confirmed', 'resend_request', 'cancelled', 'resent'],
            'features' => [
                'Ask to return from order',
                'Decide: confirm cancel or ask resend',
                'Portal notifications cache (deliveries, rider notes, cancellation requests)',
                'Scan stuck parcels (~3 days quiet by default)',
                'Parcel notes history + rider-callback (SteadFast)',
            ],
            'package' => 'courier_automation',
        ],
        'messenger' => [
            'human_inbox' => true,
            'single_page_v1' => true,
            'lead_labels' => ['new', 'engaged', 'qualified', 'negotiation', 'nurture', 'converted', 'lost', 'spam'],
            'media' => ['text', 'image', 'video', 'voice', 'pdf', 'reply_to', 'reactions', 'typing'],
            'sales_agent' => [
                'modes' => ['off', 'ai_semi', 'ai_full'],
                'public_claim' => 'AI Semi suggestions are OK to soft-mention when package allows; treat as beta',
                'ai_full_gate' => 'Readiness ≥80% + merchant unlock + confidence — never claim unlocked for all',
                'package' => 'messenger_sales_agent and/or ai_intelligence',
            ],
        ],
        'do_not_claim' => [
            'Meta AI Bot as a separate live product',
            'AI Full auto-reply as default for all merchants',
            'Pathao or RedX Return Requests / portal Notifications hub (SteadFast only)',
            'Multi-Page Messenger inbox (single Page v1)',
            'Paperfly auto-entry as shipped',
            'Fake #1 / fake AggregateRating / wooeasylife.com CTAs',
        ],
    ],

    /*
    | Map each content cluster → landing/SEO pages (config/seo.php keys + paths).
    */
    'cluster_landing' => [
        'fake_order' => [
            'primary_path' => '/fake-order-protection',
            'seo_pages' => ['fake_order_protection', 'ki_vabe_fake_order_atkabo', 'fake_customer_check', 'return_loss_calculator'],
            'related_paths' => ['/bd-fraud-checker', '/return-loss-calculator', '/ki-vabe-fake-order-atkabo', '/fake-customer-check', '/ads-roas-calculator', '/pricing'],
            'must_link_paths' => ['/fake-order-protection', '/return-loss-calculator'],
            'claims' => [
                'মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি চেক',
                'চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট',
                'রিটার্ন লস ক্যালকুলেটর দিয়ে মাসিক লস হিসাব',
                'মাল্টি-লেয়ার ফেক অর্ডার প্রোটেকশন — শুধু টুল নয়',
            ],
            'angle_hint' => 'কিভাবে ফেক অর্ডার আটকাবো — চেক → যাচাই → ব্লক + রিটার্ন লস হিসাব',
        ],
        'fraud_checker' => [
            'primary_path' => '/bd-fraud-checker',
            'seo_pages' => ['bd_fraud_checker', 'pathao_fraud_check', 'steadfast_fraud_check', 'redx_fraud_check', 'bd_courier_ratio_checker'],
            'related_paths' => ['/pathao-fraud-check', '/steadfast-fraud-check', '/redx-fraud-check', '/bd-courier-ratio-checker', '/fraudbd-alternative', '/return-loss-calculator', '/fake-order-protection', '/pricing'],
            'must_link_paths' => ['/bd-fraud-checker'],
            'claims' => [
                'ফোন নম্বর দিয়ে Pathao, Steadfast, RedX হিস্টোরি',
                'অ্যাকাউন্ট ছাড়াই দৈনিক সীমিত ফ্রি চেক',
                'সাকসেস রেট / রিটার্ন রেট দেখে অর্ডার কনফার্ম সিদ্ধান্ত',
            ],
            'angle_hint' => 'Courier Fraud Checker BD — অর্ডার কনফার্মের আগে নম্বর চেক',
        ],
        'return_loss' => [
            'primary_path' => '/return-loss-calculator',
            'seo_pages' => ['return_loss_calculator', 'fake_order_protection'],
            'related_paths' => ['/bd-fraud-checker', '/fake-order-protection', '/ads-roas-calculator', '/pricing'],
            'must_link_paths' => ['/return-loss-calculator', '/bd-fraud-checker'],
            'claims' => [
                'দৈনিক অর্ডার ও রিটার্ন রেট দিয়ে মাসিক লস হিসাব',
                'ফেক অর্ডার আটকালে সাশ্রয় vs সাবস্ক্রিপশন খরচ',
                'রিটার্ন লস কমাতে ফ্রড চেক + প্রোটেকশন',
            ],
            'angle_hint' => 'রিটার্ন লস ক্যালকুলেটর — COD সেলারের মাসিক খরচ ও সাশ্রয়',
        ],
        'checkout_protection' => [
            'primary_path' => '/fake-order-protection',
            'seo_pages' => ['fake_order_protection', 'fake_customer_check'],
            'related_paths' => ['/bd-fraud-checker', '/ki-vabe-fake-order-atkabo', '/return-loss-calculator', '/pricing'],
            'must_link_paths' => ['/fake-order-protection', '/bd-fraud-checker'],
            'claims' => [
                'চেকআউট OTP দিয়ে ভুয়া নম্বর কমান',
                'ডুপ্লিকেট অর্ডার ব্লক ও ব্ল্যাকলিস্ট',
                'ফ্রড চেক + চেকআউট সুরক্ষা একসাথে',
            ],
            'angle_hint' => 'চেকআউট OTP ও ব্লক দিয়ে ফেক কাস্টমার আটকানো',
        ],
        'courier' => [
            'primary_path' => '/steadfast-integration',
            'seo_pages' => ['steadfast_integration', 'steadfast_return_hub', 'courier_auto_entry', 'courier_charge_calculator'],
            'related_paths' => [
                '/steadfast-return-hub',
                '/courier-auto-entry',
                '/courier-charge-calculator',
                '/steadfast-fraud-check',
                '/bd-fraud-checker',
                '/return-loss-calculator',
                '/pricing',
            ],
            'must_link_paths' => ['/steadfast-integration', '/steadfast-return-hub', '/courier-auto-entry'],
            'claims' => [
                'Pathao, SteadFast, RedX অটো পার্সেল এন্ট্রি + ওয়েবহুক',
                'SteadFast Courier হাব: Return Requests (Ask to return → Decide cancel/resend)',
                'SteadFast Notifications: portal নোট ক্যাশ + stuck parcel স্ক্যান (~৩ দিন)',
                'SteadFast পার্সেল নোট হিস্ট্রি ও রাইডার কলব্যাক',
                'কুরিয়ার চার্জ ক্যালকুলেটর দিয়ে রেট তুলনা',
                'অর্ডার কনফার্ম → কুরিয়ার এন্ট্রি স্বয়ংক্রিয় (ফ্রড চেকের পর)',
            ],
            'angle_hint' => 'SteadFast ইন্টিগ্রেশন + Return/Stuck হাব + মাল্টি-কুরিয়ার অটো এন্ট্রি — COD অপস',
        ],
        'messenger' => [
            'primary_path' => '/woocommerce-facebook-messenger',
            'seo_pages' => ['woocommerce_facebook_messenger', 'pricing', 'home'],
            'related_paths' => ['/woocommerce-facebook-messenger', '/pricing', '/fake-order-protection', '/bd-fraud-checker', '/courier-auto-entry'],
            'must_link_paths' => ['/woocommerce-facebook-messenger', '/pricing'],
            'claims' => [
                'WordPress অ্যাডমিনে Facebook Page Messenger ইনবক্স (কানেক্ট/ডিসকানেক্ট)',
                'টেক্সট · ইমেজ · ভয়েস · রিপ্লাই · টাইপিং — মানব ইনবক্স',
                'Lead labels: new → nurture → converted (ম্যানুয়াল ওভাররাইড)',
                'থ্রেড থেকে WooCommerce অর্ডার লিংক (Sales Agent path; admin_approval ডিফল্ট)',
                'AI Semi: সাজেশন ড্রাফট (প্যাকেজ-গেটেড, beta) — AI Full সবার জন্য আনলক নয়',
            ],
            'angle_hint' => 'মেসেঞ্জার ইনবক্স → অর্ডার — BD Facebook পেজ COD সেলার; স্টোরফ্রন্ট চ্যাট বাবল নয়',
        ],
        'courier_charge' => [
            'primary_path' => '/courier-charge-calculator',
            'seo_pages' => ['courier_charge_calculator', 'courier_auto_entry'],
            'related_paths' => ['/courier-auto-entry', '/bd-fraud-checker', '/pricing'],
            'must_link_paths' => ['/courier-charge-calculator', '/courier-auto-entry'],
            'claims' => [
                'Pathao · Steadfast · RedX আনুমানিক/লাইভ চার্জ তুলনা',
                'ঢাকা / সাবআরবান / বাইরের জোন ও ওজন দিয়ে হিসাব',
                'কনফার্ম হলে অটো এন্ট্রি — প্যানেলে বারবার চার্জ হিসাব নয়',
            ],
            'angle_hint' => 'কুরিয়ার চার্জ ক্যালকুলেটর — Pathao Steadfast RedX রেট তুলনা',
        ],
        'missing_order' => [
            'primary_path' => '/',
            'seo_pages' => ['home'],
            'related_paths' => ['/pricing', '/bd-fraud-checker', '/return-loss-calculator', '/ads-roas-calculator'],
            'must_link_paths' => ['/', '/bd-fraud-checker'],
            'claims' => [
                'মিসিং / অসম্পূর্ণ চেকআউট অর্ডার আলাদা করে দেখা',
                'ওয়ান-ক্লিক মিসিং অর্ডার থেকে WooCommerce অর্ডার',
                'হারানো বিক্রি রিকভারি — শুধু ফ্রড টুল নয়',
            ],
            'angle_hint' => 'হারানো অর্ডার রিকভারি — cart/checkout drop-off',
        ],
        'facebook_ads' => [
            'primary_path' => '/ads-roas-calculator',
            'seo_pages' => ['ads_roas_calculator', 'fake_order_protection'],
            'related_paths' => ['/fake-order-protection', '/bd-fraud-checker', '/return-loss-calculator', '/pricing'],
            'must_link_paths' => ['/ads-roas-calculator', '/fake-order-protection'],
            'claims' => [
                'রিপোর্টেড vs আসল Facebook Ads ROAS হিসাব',
                'ফেক Purchase বাদ দিয়ে পিক্সেল সুরক্ষা',
                'ফ্রড চেক + চেকআউট সুরক্ষা দিয়ে অ্যাড ROI বাড়ান',
            ],
            'angle_hint' => 'Facebook Ads ROAS ক্যালকুলেটর — ফেক Purchase ও অ্যাড বাজেট',
        ],
        'ai_orders' => [
            'primary_path' => '/',
            'seo_pages' => ['home'],
            'related_paths' => ['/courier-auto-entry', '/courier-charge-calculator', '/pricing'],
            'must_link_paths' => ['/', '/courier-auto-entry'],
            'claims' => [
                'মেসেজ / স্ক্রিনশট থেকে অর্ডার তৈরি (AI order workflow)',
                'ম্যানুয়াল অর্ডার এন্ট্রি কমান',
                'কুরিয়ার অটো এন্ট্রির সাথে অপারেশন একসাথে',
            ],
            'angle_hint' => 'মেসেজ/ইমেজ থেকে অর্ডার — AI অর্ডার ওয়ার্কফ্লো',
        ],
        'packing_print' => [
            'primary_path' => '/',
            'seo_pages' => ['home'],
            'related_paths' => ['/courier-auto-entry', '/courier-charge-calculator', '/pricing'],
            'must_link_paths' => ['/', '/courier-auto-entry'],
            'claims' => [
                'ইনভয়েস ও কুরিয়ার স্টিকার প্রিন্ট',
                'প্যাকিং স্লিপ দিয়ে অপারেশন দ্রুত',
                'কুরিয়ার অটো এন্ট্রির সাথে মিলিয়ে ওয়ার্কফ্লো',
            ],
            'angle_hint' => 'প্যাকিং / ইনভয়েস / স্টিকার প্রিন্ট',
        ],
        'multistore_app' => [
            'primary_path' => '/',
            'seo_pages' => ['home'],
            'related_paths' => ['/pricing', '/bd-fraud-checker', '/courier-auto-entry'],
            'must_link_paths' => ['/'],
            'claims' => [
                'এক ড্যাশবোর্ডে একাধিক স্টোর',
                'মোবাইল অ্যাপে অর্ডার অ্যালার্ট',
                'মাল্টিস্টোর অর্ডার মিস না করা',
            ],
            'angle_hint' => 'মাল্টিস্টোর ড্যাশবোর্ড ও মোবাইল অ্যাপ',
        ],
        'team_calls' => [
            'primary_path' => '/',
            'seo_pages' => ['home'],
            'related_paths' => ['/pricing', '/bd-fraud-checker'],
            'must_link_paths' => ['/'],
            'claims' => [
                'কাস্টমার কল হিস্ট্রি / কল ট্র্যাকিং',
                'স্টাফ ম্যানেজমেন্ট ও দায়িত্ব ভাগ',
                'টিম অপারেশন + অর্ডার স্ট্যাটাস এক জায়গায়',
            ],
            'angle_hint' => 'টিম কল ট্র্যাকিং ও স্টাফ অপারেশন',
        ],
        'operations' => [
            'primary_path' => '/',
            'seo_pages' => ['home', 'pricing'],
            'related_paths' => ['/bd-fraud-checker', '/return-loss-calculator', '/courier-charge-calculator', '/ads-roas-calculator', '/courier-auto-entry', '/fake-order-protection', '/pricing'],
            'must_link_paths' => ['/', '/bd-fraud-checker'],
            'claims' => [
                'ফ্রড চেক + ফেক অর্ডার প্রোটেকশন + কুরিয়ার অটো এন্ট্রি এক প্ল্যাটফর্ম',
                'ফ্রি SEO টুল: রিটার্ন লস · কুরিয়ার চার্জ · Ads ROAS ক্যালকুলেটর',
                '১৪ দিন ফ্রি ট্রায়াল',
            ],
            'angle_hint' => 'সম্পূর্ণ WooCommerce অপারেশন + ফ্রি টুলস — টুল-শুধু নয়',
        ],
        'woocommerce' => [
            'primary_path' => '/',
            'seo_pages' => ['home', 'pricing'],
            'related_paths' => ['/pricing', '/bd-fraud-checker', '/courier-auto-entry', '/fake-order-protection'],
            'must_link_paths' => ['/', '/pricing'],
            'claims' => [
                'WooCommerce Bangladesh অপারেশন ও প্লাগইন',
                'অটোমেশন, নোটিফিকেশন ও মোবাইল অ্যাপ',
                '১৪ দিন ফ্রি ট্রায়াল',
            ],
            'angle_hint' => 'WooCommerce Bangladesh — প্লাগইন, অটোমেশন, ম্যানেজমেন্ট',
        ],
        'cod' => [
            'primary_path' => '/fake-order-protection',
            'seo_pages' => ['fake_order_protection', 'return_loss_calculator'],
            'related_paths' => ['/bd-fraud-checker', '/return-loss-calculator', '/fake-order-protection', '/pricing'],
            'must_link_paths' => ['/fake-order-protection', '/return-loss-calculator'],
            'claims' => [
                'COD রিটার্ন রেট ও ফ্রড কমান',
                'ভেরিফিকেশন + অটোমেশন দিয়ে অর্ডার ম্যানেজমেন্ট',
                'রিটার্ন লস হিসাব করে সিদ্ধান্ত নিন',
            ],
            'angle_hint' => 'COD fraud, verification, automation ও return rate',
        ],
        'general' => [
            'primary_path' => '/',
            'seo_pages' => ['home', 'pricing'],
            'related_paths' => ['/bd-fraud-checker', '/return-loss-calculator', '/courier-charge-calculator', '/ads-roas-calculator', '/fake-order-protection', '/courier-auto-entry', '/pricing'],
            'must_link_paths' => ['/', '/bd-fraud-checker'],
            'claims' => [
                'BD fraud checker, return-loss & courier-charge calculators, Ads ROAS tool',
                'ফেক অর্ডার প্রোটেকশন + কুরিয়ার অটো এন্ট্রি',
                '১৪ দিন ফ্রি ট্রায়াল',
            ],
            'angle_hint' => 'সাধারণ BD WooCommerce গাইড — ফ্রি টুল + WooEasyLife ল্যান্ডিং সত্য',
        ],
    ],

    /*
    | Keyword/seed needles for automatic cluster detection (case-insensitive contains).
    */
    'cluster_detect_needles' => [
        'return_loss' => ['রিটার্ন লস ক্যালকুলেটর', 'return loss calculator', 'মাসিক রিটার্ন লস', 'cod return loss', 'রিটার্ন লস হিসাব', 'ফেক অর্ডার খরচ', 'রিটার্ন লস'],
        'courier_charge' => ['কুরিয়ার চার্জ ক্যালকুলেটর', 'courier charge calculator', 'pathao চার্জ', 'steadfast চার্জ', 'redx চার্জ', 'ডেলিভারি চার্জ হিসাব', 'কুরিয়ার রেট'],
        'fake_order' => ['ফেক অর্ডার', 'fake order', 'fake-order', 'পার্সেল রিটার্ন', 'cod fraud', 'অর্ডার আটকা'],
        'fraud_checker' => ['ফ্রড চেকার', 'fraud checker', 'কুরিয়ার হিস্টোরি', 'courier history', 'pathao fraud', 'steadfast fraud', 'redx fraud', 'সাকসেস রেট', 'courier ratio', 'fraudbd'],
        'checkout_protection' => ['চেকআউট ওটিপি', 'checkout otp', 'otp', 'ডুপ্লিকেট অর্ডার', 'ব্ল্যাকলিস্ট', 'duplicate order', 'fake customer block'],
        'courier' => [
            'কুরিয়ার অটো', 'courier auto', 'অটো এন্ট্রি', 'auto entry', 'pathao entry', 'redx entry',
            'পার্সেল এন্ট্রি', 'পার্সেল নোট', 'parcel note', 'parcel note history', 'steadfast note',
            'steadfast return', 'ask to return', 'return request', 'stuck parcel', 'রিটার্ন রিকোয়েস্ট',
            'পার্সেল আটকে', 'steadfast integration', 'courier notification',
        ],
        'messenger' => [
            'messenger inbox', 'facebook messenger', 'মেসেঞ্জার ইনবক্স', 'মেসেঞ্জার থেকে অর্ডার',
            'facebook page chat', 'messenger woocommerce', 'nurture lead', 'ai semi', 'মেসেঞ্জার অর্ডার',
        ],
        'missing_order' => ['হারানো অর্ডার', 'missing order', 'মিসিং অর্ডার', 'abandoned checkout', 'অসম্পূর্ণ অর্ডার'],
        'facebook_ads' => ['facebook ads', 'ফেসবুক অ্যাড', 'facebook pixel', 'পিক্সেল', 'meta ads', 'purchase event', 'roas', 'ads roas', 'ফেক purchase'],
        'ai_orders' => ['ai order', 'মেসেজ থেকে অর্ডার', 'screenshot থেকে অর্ডার', 'image to order', 'মেসেজ অর্ডার'],
        'packing_print' => ['ইনভয়েস', 'invoice', 'packing', 'স্টিকার প্রিন্ট', 'packing slip'],
        'multistore_app' => ['মাল্টিস্টোর', 'multistore', 'মোবাইল অ্যাপ', 'mobile app', 'এক ড্যাশবোর্ড'],
        'team_calls' => ['কল হিস্ট্রি', 'call tracking', 'স্টাফ', 'team call', 'customer call'],
        'operations' => ['অপারেশন', 'ড্যাশবোর্ড', 'order management', 'অর্ডার ম্যানেজমেন্ট', 'cod সেলার টুল'],
        'woocommerce' => ['woocommerce bangladesh', 'woocommerce plugin', 'woocommerce automation', 'woocommerce app', 'woocommerce management'],
        'cod' => ['cod return', 'cod fraud', 'cod verification', 'cod automation', 'cash on delivery', 'ক্যাশ অন ডেলিভারি'],
    ],

    /*
    | Seed queries for Google Suggest (gl=bd) when generating keywords per cluster.
    */
    'cluster_seed_queries' => [
        'fake_order' => [
            'Stop Fake Orders', 'OTP Verification', 'Duplicate Orders', 'Blacklist Customers',
            'Fake Customer Detection', 'High Risk Customer', 'ফেক অর্ডার', 'কিভাবে ফেক অর্ডার আটকাবো', 'COD fraud check',
        ],
        'fraud_checker' => [
            'What is Fraud Checker', 'Best Fraud Checker BD', 'Free Fraud Checker', 'FraudBD Alternative',
            'Courier Fraud Checker', 'Fake Order Detection', 'Phone Number Fraud Check',
            'ফ্রড চেকার', 'কুরিয়ার হিস্টোরি চেক', 'pathao fraud check',
        ],
        'return_loss' => ['রিটার্ন লস', 'রিটার্ন লস ক্যালকুলেটর', 'COD রিটার্ন খরচ'],
        'checkout_protection' => ['চেকআউট OTP', 'ডুপ্লিকেট অর্ডার ব্লক', 'fake customer block'],
        'courier' => [
            'Steadfast Integration', 'Pathao Integration', 'RedX Integration', 'Auto Courier Entry',
            'Courier Tracking', 'কুরিয়ার অটো এন্ট্রি', 'পার্সেল নোট হিস্ট্রি', 'steadfast parcel note',
            'steadfast return request', 'steadfast ask to return', 'stuck parcel steadfast',
            'SteadFast রিটার্ন রিকোয়েস্ট', 'পার্সেল আটকে গেছে', 'steadfast portal notification',
        ],
        'messenger' => [
            'WooCommerce Facebook Messenger inbox', 'মেসেঞ্জার থেকে অর্ডার', 'facebook page messenger wordpress',
            'messenger order woocommerce bangladesh', 'Facebook Page chat WooCommerce',
        ],
        'courier_charge' => ['কুরিয়ার চার্জ', 'pathao ডেলিভারি চার্জ', 'steadfast প্রাইসিং'],
        'missing_order' => ['হারানো অর্ডার', 'missing order WooCommerce', 'abandoned checkout'],
        'facebook_ads' => ['Facebook Ads ROAS', 'পিক্সেল প্রোটেকশন', 'ফেক purchase facebook'],
        'ai_orders' => ['মেসেজ থেকে অর্ডার', 'AI order WooCommerce', 'screenshot থেকে অর্ডার'],
        'packing_print' => ['ইনভয়েস প্রিন্ট', 'কুরিয়ার স্টিকার প্রিন্ট', 'packing slip'],
        'multistore_app' => ['মাল্টিস্টোর ড্যাশবোর্ড', 'WooCommerce mobile app', 'এক ড্যাশবোর্ডে সব স্টোর'],
        'team_calls' => ['কল হিস্ট্রি', 'true call identifier', 'স্টাফ ম্যানেজমেন্ট'],
        'operations' => ['WooCommerce অপারেশন', 'অর্ডার ম্যানেজমেন্ট বাংলাদেশ', 'COD সেলার টুল'],
        'woocommerce' => [
            'WooCommerce Bangladesh', 'WooCommerce Plugins', 'WooCommerce Automation',
            'WooCommerce Mobile App', 'WooCommerce Management', 'WooCommerce Notifications', 'WooCommerce বাংলাদেশ',
        ],
        'cod' => [
            'COD Return Rate', 'COD Fraud', 'COD Verification', 'COD Automation',
            'COD Order Management', 'COD ব্যবসা', 'ক্যাশ অন ডেলিভারি',
        ],
        'general' => ['WooCommerce বাংলাদেশ', 'COD ব্যবসা', 'অনলাইন ব্যবসা গাইড'],
    ],

    'static_internal_links' => [
        [
            'path' => '/',
            'title' => 'WooEasyLife হোম',
            'anchor_hints' => ['WooEasyLife', 'WooCommerce অপারেশন প্ল্যাটফর্ম'],
        ],
        [
            'path' => '/return-loss-calculator',
            'title' => 'রিটার্ন লস ক্যালকুলেটর',
            'anchor_hints' => ['রিটার্ন লস ক্যালকুলেটর', 'COD রিটার্ন লস হিসাব', 'মাসিক রিটার্ন লস'],
        ],
        [
            'path' => '/courier-charge-calculator',
            'title' => 'কুরিয়ার চার্জ ক্যালকুলেটর',
            'anchor_hints' => ['কুরিয়ার চার্জ ক্যালকুলেটর', 'Pathao Steadfast চার্জ', 'ডেলিভারি চার্জ হিসাব'],
        ],
        [
            'path' => '/ads-roas-calculator',
            'title' => 'Facebook Ads ROAS ক্যালকুলেটর',
            'anchor_hints' => ['Facebook Ads ROAS ক্যালকুলেটর', 'ফেক Purchase ROAS', 'পিক্সেল ROAS'],
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
            'path' => '/ki-vabe-fake-order-atkabo',
            'title' => 'কিভাবে ফেক অর্ডার আটকাবো',
            'anchor_hints' => ['কিভাবে ফেক অর্ডার আটকাবো', 'ফেক অর্ডার গাইড'],
        ],
        [
            'path' => '/fake-customer-check',
            'title' => 'Fake Customer Check',
            'anchor_hints' => ['ফেক কাস্টমার চেক', 'fake customer check'],
        ],
        [
            'path' => '/bd-courier-ratio-checker',
            'title' => 'BD Courier Ratio Checker',
            'anchor_hints' => ['কুরিয়ার রেশিও চেক', 'সাকসেস রেট চেক'],
        ],
        [
            'path' => '/courier-auto-entry',
            'title' => 'Courier Auto Entry',
            'anchor_hints' => ['কুরিয়ার অটো এন্ট্রি', 'Pathao Steadfast RedX'],
        ],
        [
            'path' => '/steadfast-return-hub',
            'title' => 'SteadFast Return Hub',
            'anchor_hints' => ['SteadFast Return Hub', 'return request', 'stuck parcel', 'Ask to return'],
        ],
        [
            'path' => '/woocommerce-facebook-messenger',
            'title' => 'Facebook Messenger ইনবক্স',
            'anchor_hints' => ['Messenger ইনবক্স', 'facebook page chat', 'woocommerce messenger'],
        ],
        [
            'path' => '/steadfast-integration',
            'title' => 'Steadfast Integration',
            'anchor_hints' => [
                'Steadfast ইন্টিগ্রেশন',
                'steadfast return request',
                'Ask to return',
                'stuck parcel',
                'SteadFast WooCommerce plugin',
            ],
        ],
        [
            'path' => '/en/steadfast-integration',
            'title' => 'Steadfast Integration (EN)',
            'anchor_hints' => ['Steadfast integration', 'SteadFast return requests', 'stuck parcel scan'],
        ],
        [
            'path' => '/pathao-fraud-check',
            'title' => 'Pathao Fraud Check',
            'anchor_hints' => ['Pathao fraud check', 'Pathao হিস্টোরি'],
        ],
        [
            'path' => '/steadfast-fraud-check',
            'title' => 'Steadfast Fraud Check',
            'anchor_hints' => ['Steadfast fraud check', 'Steadfast হিস্টোরি'],
        ],
        [
            'path' => '/redx-fraud-check',
            'title' => 'RedX Fraud Check',
            'anchor_hints' => ['RedX fraud check', 'RedX হিস্টোরি'],
        ],
        [
            'path' => '/fraudbd-alternative',
            'title' => 'FraudBD Alternative',
            'anchor_hints' => ['FraudBD বিকল্প', 'fraud checker alternative'],
        ],
        [
            'path' => '/pricing',
            'title' => 'প্রাইসিং',
            'anchor_hints' => ['WooEasyLife প্রাইসিং', 'প্যাকেজ', 'ফ্রি ট্রায়াল'],
        ],
    ],

    'persona' => [
        'product' => 'WooEasyLife',
        'audience' => 'বাংলাদেশের WooCommerce / Facebook / COD সেলার',
        'tone' => 'মেসেঞ্জার-স্টাইল সেলার টক — বাস্তবসম্মত সহজ বাংলা; কর্পোরেট/এআই ব্লগ টোন নয়',
        'founder' => 'Muhibbullah Ansary, Developer of WooEasyLife',
        'voice_do' => [
            'কথ্য বাংলা (করেন/হয়/দেখুন), ছোট অনুচ্ছেদ, বাস্তব COD উদাহরণ',
            'সমস্যা → ধাপ → টুল — গল্পের মতো; প্রতিটি সেকশন কীওয়ার্ড দিয়ে শুরু নয়',
            'চেকলিস্ট ছাড়া অপ্রয়োজনীয় বুলেট এড়ানো',
        ],
        'voice_dont' => [
            'আজকের ডিজিটাল যুগে / কম্প্রিহেনসিভ স্ট্র্যাটেজি / গেমচেঞ্জার / সিমলেস',
            'In today\'s digital age / It is important to note / In conclusion / Let\'s dive in',
            'প্রতিটি H2-কে লিস্টিকল বানানো বা সারফার-স্টাইল ফিলার',
            'Meta AI Bot / unlocked AI Full for everyone / Pathao-RedX return hub as live / Paperfly auto-entry',
            'wooeasylife.com links or fake No.1 / AggregateRating',
        ],
    ],

    /*
    | Preferred inventory blog themes for Auto / smart picker (P0 feature launches).
    */
    'preferred_feature_themes' => [
        [
            'cluster' => 'courier',
            'seed' => 'SteadFast return request Decide workflow',
            'cta' => '/steadfast-integration',
            'priority' => 100,
        ],
        [
            'cluster' => 'courier',
            'seed' => 'SteadFast stuck parcel notification scan',
            'cta' => '/steadfast-integration',
            'priority' => 95,
        ],
        [
            'cluster' => 'messenger',
            'seed' => 'WooCommerce Facebook Messenger inbox Bangladesh',
            'cta' => '/pricing',
            'priority' => 80,
        ],
    ],

    /*
    | One-click auto pipeline (orchestrator + per-step review). Manual wizard unchanged.
    */
    'auto' => [
        'enabled' => (bool) env('BLOG_AI_AUTO_ENABLED', true),
        'create_post' => (bool) env('BLOG_AI_AUTO_CREATE_POST', true),
        'max_revisions_per_step' => (int) env('BLOG_AI_AUTO_MAX_REVISIONS', 2),
        'pass_score' => (int) env('BLOG_AI_AUTO_PASS_SCORE', 70),
        /*
        | Soft-pass drafts (failed SEO after max revisions) never score above this.
        | When allow_draft_soft_pass is false, Auto Create fails instead of soft-passing.
        */
        'allow_draft_soft_pass' => filter_var(env('BLOG_AI_AUTO_ALLOW_DRAFT_SOFT_PASS', true), FILTER_VALIDATE_BOOLEAN),
        'soft_pass_score_cap' => (int) env('BLOG_AI_AUTO_SOFT_PASS_CAP', 59),
        'hooks_to_select' => (int) env('BLOG_AI_AUTO_HOOKS_SELECT', 1),
        /*
        | When false, Auto Create skips cover generation (faster/safer on shared hosting).
        | Manual wizard still uses blog_ai.image_enabled / BLOG_AI_IMAGE_ENABLED.
        */
        'generate_image' => filter_var(env('BLOG_AI_AUTO_IMAGE', false), FILTER_VALIDATE_BOOLEAN),
        'auto_approve_image_on_fail' => (bool) env('BLOG_AI_AUTO_APPROVE_IMAGE', true),
        'image_max_attempts' => (int) env('BLOG_AI_AUTO_IMAGE_ATTEMPTS', 1),
        'busy_stale_minutes' => (int) env('BLOG_AI_AUTO_BUSY_STALE_MINUTES', 25),
        /*
        | Pending (never started by worker) runs auto-clear sooner than in-progress ones.
        */
        'pending_stale_minutes' => (int) env('BLOG_AI_AUTO_PENDING_STALE_MINUTES', 10),
        'use_llm_review' => (bool) env('BLOG_AI_AUTO_LLM_REVIEW', true),
        /*
        | null = require queue only in production. true/false force either way.
        | Set BLOG_AI_AUTO_REQUIRE_QUEUE=false to allow sync locally/staging.
        */
        'require_queue' => env('BLOG_AI_AUTO_REQUIRE_QUEUE'),
        'one_active_run_per_user' => (bool) env('BLOG_AI_AUTO_ONE_ACTIVE', true),
        /*
        | One-click Smart Post: sync GSC/learning → pick best topic → full auto draft.
        */
        'smart_one_click' => filter_var(env('BLOG_AI_SMART_ONE_CLICK', true), FILTER_VALIDATE_BOOLEAN),
        'smart_sync_learning' => filter_var(env('BLOG_AI_SMART_SYNC_LEARNING', true), FILTER_VALIDATE_BOOLEAN),
        /*
        | When GSC rank opportunities exist, Smart Post ignores cluster/learning guesses
        | and only picks real Search Console queries (free + real demand).
        */
        'prefer_gsc' => filter_var(env('BLOG_AI_SMART_PREFER_GSC', true), FILTER_VALIDATE_BOOLEAN),
        /*
        | When true, Smart One-Click refuses soft-pass drafts (classic Auto unchanged).
        | Keep false on live until you are ready for stricter Auto quality.
        */
        'smart_strict_draft' => filter_var(env('BLOG_AI_SMART_STRICT_DRAFT', false), FILTER_VALIDATE_BOOLEAN),
        'weights' => [
            'opportunity' => 15,
            'outline' => 15,
            'seo' => 30,
            'content' => 25,
            'image' => 15,
        ],
        'progress' => [
            'sync' => 8,
            'intake' => 5,
            'research' => 14,
            'hooks' => 11,
            'outline' => 14,
            'draft' => 26,
            'image' => 17,
            'finalize' => 5,
        ],
    ],
];
