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

    'min_body_words' => 800,

    'hooks_count' => 10,

    'internal_links_min' => 2,

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
        'require_lists' => true,
        /*
        | Hard publish gates only — everything else is soft-warned in the CMS.
        */
        'enforce_on_publish' => [
            'ai_ready' => false,
            'focus_keyword_required' => false,
            'has_og_or_content_image' => false,
            'has_internal_link' => false,
            'duplicate_focus_keyword' => true,
            'duplicate_slug' => false, // DB unique rule already covers slug
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

    'clusters' => [
        'fake_order' => 'ফেক অর্ডার / COD fraud',
        'fraud_checker' => 'ফ্রড চেকার / Courier history',
        'checkout_protection' => 'চেকআউট সুরক্ষা / OTP & block',
        'courier' => 'কুরিয়ার / অটো এন্ট্রি',
        'missing_order' => 'হারানো অর্ডার / Missing order',
        'facebook_ads' => 'Facebook Ads / Pixel',
        'ai_orders' => 'AI অর্ডার / Message & image to order',
        'packing_print' => 'প্যাকিং / Invoice & sticker',
        'multistore_app' => 'মাল্টিস্টোর / Mobile app',
        'team_calls' => 'টিম / Call tracking',
        'operations' => 'অপারেশন / ড্যাশবোর্ড',
        'general' => 'সাধারণ WooCommerce BD',
    ],

    /*
    | Map each content cluster → landing/SEO pages (config/seo.php keys + paths).
    | Auto + manual prompts use this so posts stay aligned with live landing copy.
    */
    'cluster_landing' => [
        'fake_order' => [
            'primary_path' => '/fake-order-protection',
            'seo_pages' => ['fake_order_protection', 'ki_vabe_fake_order_atkabo', 'fake_customer_check'],
            'related_paths' => ['/bd-fraud-checker', '/ki-vabe-fake-order-atkabo', '/fake-customer-check', '/pricing'],
            'must_link_paths' => ['/fake-order-protection'],
            'claims' => [
                'মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি চেক',
                'চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট',
                'মাল্টি-লেয়ার ফেক অর্ডার প্রোটেকশন — শুধু টুল নয়',
            ],
            'angle_hint' => 'কিভাবে ফেক অর্ডার আটকাবো — চেক → যাচাই → ব্লক প্র্যাকটিক্যাল গাইড',
        ],
        'fraud_checker' => [
            'primary_path' => '/bd-fraud-checker',
            'seo_pages' => ['bd_fraud_checker', 'pathao_fraud_check', 'steadfast_fraud_check', 'redx_fraud_check', 'bd_courier_ratio_checker'],
            'related_paths' => ['/pathao-fraud-check', '/steadfast-fraud-check', '/redx-fraud-check', '/bd-courier-ratio-checker', '/fraudbd-alternative', '/pricing'],
            'must_link_paths' => ['/bd-fraud-checker'],
            'claims' => [
                'ফোন নম্বর দিয়ে Pathao, Steadfast, RedX হিস্টোরি',
                'অ্যাকাউন্ট ছাড়াই দৈনিক সীমিত ফ্রি চেক',
                'সাকসেস রেট / রিটার্ন রেট দেখে অর্ডার কনফার্ম সিদ্ধান্ত',
            ],
            'angle_hint' => 'Courier Fraud Checker BD — অর্ডার কনফার্মের আগে নম্বর চেক',
        ],
        'checkout_protection' => [
            'primary_path' => '/fake-order-protection',
            'seo_pages' => ['fake_order_protection', 'fake_customer_check'],
            'related_paths' => ['/bd-fraud-checker', '/ki-vabe-fake-order-atkabo', '/pricing'],
            'must_link_paths' => ['/fake-order-protection'],
            'claims' => [
                'চেকআউট OTP দিয়ে ভুয়া নম্বর কমান',
                'ডুপ্লিকেট অর্ডার ব্লক ও ব্ল্যাকলিস্ট',
                'ফ্রড চেক + চেকআউট সুরক্ষা একসাথে',
            ],
            'angle_hint' => 'চেকআউট OTP ও ব্লক দিয়ে ফেক কাস্টমার আটকানো',
        ],
        'courier' => [
            'primary_path' => '/courier-auto-entry',
            'seo_pages' => ['courier_auto_entry'],
            'related_paths' => ['/bd-fraud-checker', '/pricing'],
            'must_link_paths' => ['/courier-auto-entry'],
            'claims' => [
                'Pathao, Steadfast, RedX অটো পার্সেল এন্ট্রি',
                'ম্যানুয়াল কুরিয়ার প্যানেল কপি-পেস্ট কমান',
                'অর্ডার কনফার্ম → কুরিয়ার এন্ট্রি স্বয়ংক্রিয়',
            ],
            'angle_hint' => 'কুরিয়ার অটো এন্ট্রি — COD অপারেশন দ্রুত',
        ],
        'missing_order' => [
            'primary_path' => '/',
            'seo_pages' => ['home'],
            'related_paths' => ['/pricing', '/bd-fraud-checker'],
            'must_link_paths' => ['/'],
            'claims' => [
                'মিসিং / অসম্পূর্ণ চেকআউট অর্ডার আলাদা করে দেখা',
                'ওয়ান-ক্লিক মিসিং অর্ডার থেকে WooCommerce অর্ডার',
                'হারানো বিক্রি রিকভারি — শুধু ফ্রড টুল নয়',
            ],
            'angle_hint' => 'হারানো অর্ডার রিকভারি — cart/checkout drop-off',
        ],
        'facebook_ads' => [
            'primary_path' => '/',
            'seo_pages' => ['home'],
            'related_paths' => ['/fake-order-protection', '/bd-fraud-checker', '/pricing'],
            'must_link_paths' => ['/'],
            'claims' => [
                'Facebook / COD অ্যাড স্পেন্ড ফেক অর্ডারে নষ্ট কমান',
                'পিক্সেল ও Purchase ইভেন্ট সুরক্ষা (প্রোডাক্ট ফিচার অনুযায়ী)',
                'ফ্রড চেক + চেকআউট সুরক্ষা দিয়ে অ্যাড ROI বাড়ান',
            ],
            'angle_hint' => 'Facebook Ads + COD — ফেক অর্ডার ও রিটার্ন লস কমানো',
        ],
        'ai_orders' => [
            'primary_path' => '/',
            'seo_pages' => ['home'],
            'related_paths' => ['/courier-auto-entry', '/pricing'],
            'must_link_paths' => ['/'],
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
            'related_paths' => ['/courier-auto-entry', '/pricing'],
            'must_link_paths' => ['/'],
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
            'related_paths' => ['/pricing'],
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
            'related_paths' => ['/pricing'],
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
            'related_paths' => ['/bd-fraud-checker', '/courier-auto-entry', '/pricing'],
            'must_link_paths' => ['/'],
            'claims' => [
                'ফ্রড চেক + ফেক অর্ডার প্রোটেকশন + কুরিয়ার অটো এন্ট্রি এক প্ল্যাটফর্ম',
                '১৪ দিন ফ্রি ট্রায়াল',
                'বাংলাদেশ COD / WooCommerce সেলারদের জন্য অপারেশন টুল',
            ],
            'angle_hint' => 'সম্পূর্ণ WooCommerce অপারেশন — টুল-শুধু নয়',
        ],
        'general' => [
            'primary_path' => '/',
            'seo_pages' => ['home', 'pricing'],
            'related_paths' => ['/bd-fraud-checker', '/fake-order-protection', '/courier-auto-entry', '/pricing'],
            'must_link_paths' => ['/'],
            'claims' => [
                'BD fraud checker, fake order protection, courier auto-entry',
                '১৪ দিন ফ্রি ট্রায়াল',
                'বাংলাদেশ WooCommerce / Facebook / COD সেলার',
            ],
            'angle_hint' => 'সাধারণ BD WooCommerce গাইড — WooEasyLife ল্যান্ডিং সত্য মেনে',
        ],
    ],

    /*
    | Keyword/seed needles for automatic cluster detection (case-insensitive contains).
    */
    'cluster_detect_needles' => [
        'fake_order' => ['ফেক অর্ডার', 'fake order', 'fake-order', 'পার্সেল রিটার্ন', 'রিটার্ন লস', 'cod fraud', 'অর্ডার আটকা'],
        'fraud_checker' => ['ফ্রড চেকার', 'fraud checker', 'কুরিয়ার হিস্টোরি', 'courier history', 'pathao fraud', 'steadfast fraud', 'redx fraud', 'সাকসেস রেট', 'courier ratio', 'fraudbd'],
        'checkout_protection' => ['চেকআউট ওটিপি', 'checkout otp', 'otp', 'ডুপ্লিকেট অর্ডার', 'ব্ল্যাকলিস্ট', 'duplicate order', 'fake customer block'],
        'courier' => ['কুরিয়ার অটো', 'courier auto', 'অটো এন্ট্রি', 'auto entry', 'steadfast', 'pathao entry', 'redx entry', 'পার্সেল এন্ট্রি'],
        'missing_order' => ['হারানো অর্ডার', 'missing order', 'মিসিং অর্ডার', 'abandoned checkout', 'অসম্পূর্ণ অর্ডার'],
        'facebook_ads' => ['facebook ads', 'ফেসবুক অ্যাড', 'facebook pixel', 'পিক্সেল', 'meta ads', 'purchase event'],
        'ai_orders' => ['ai order', 'মেসেজ থেকে অর্ডার', 'screenshot থেকে অর্ডার', 'image to order', 'মেসেজ অর্ডার'],
        'packing_print' => ['ইনভয়েস', 'invoice', 'packing', 'স্টিকার প্রিন্ট', 'packing slip', 'প্রিন্ট'],
        'multistore_app' => ['মাল্টিস্টোর', 'multistore', 'মোবাইল অ্যাপ', 'mobile app', 'এক ড্যাশবোর্ড'],
        'team_calls' => ['কল হিস্ট্রি', 'call tracking', 'স্টাফ', 'team call', 'customer call'],
        'operations' => ['অপারেশন', 'ড্যাশবোর্ড', 'order management', 'অর্ডার ম্যানেজমেন্ট', 'cod সেলার টুল'],
    ],

    /*
    | Seed queries for Google Suggest (gl=bd) when generating keywords per cluster.
    */
    'cluster_seed_queries' => [
        'fake_order' => ['ফেক অর্ডার', 'কিভাবে ফেক অর্ডার আটকাবো', 'COD fraud check'],
        'fraud_checker' => ['কুরিয়ার হিস্টোরি চেক', 'ফ্রড চেকার', 'pathao fraud check'],
        'checkout_protection' => ['চেকআউট ওটিপি', 'ডুপ্লিকেট অর্ডার ব্লক', 'fake customer block'],
        'courier' => ['কুরিয়ার অটো এন্ট্রি', 'pathao steadfast redx', 'WooCommerce courier'],
        'missing_order' => ['হারানো অর্ডার', 'missing order WooCommerce', 'abandoned checkout'],
        'facebook_ads' => ['Facebook pixel purchase', 'পিক্সেল প্রোটেকশন', 'Facebook ads COD'],
        'ai_orders' => ['মেসেজ থেকে অর্ডার', 'AI order WooCommerce', 'screenshot থেকে অর্ডার'],
        'packing_print' => ['ইনভয়েস প্রিন্ট', 'কুরিয়ার স্টিকার প্রিন্ট', 'packing slip'],
        'multistore_app' => ['মাল্টিস্টোর ড্যাশবোর্ড', 'WooCommerce mobile app', 'এক ড্যাশবোর্ডে সব স্টোর'],
        'team_calls' => ['কল হিস্ট্রি', 'customer call identifier', 'স্টাফ ম্যানেজমেন্ট'],
        'operations' => ['WooCommerce অপারেশন', 'অর্ডার ম্যানেজমেন্ট বাংলাদেশ', 'COD সেলার টুল'],
        'general' => ['WooCommerce বাংলাদেশ', 'COD ব্যবসা', 'অনলাইন ব্যবসা গাইড'],
    ],

    'static_internal_links' => [
        [
            'path' => '/',
            'title' => 'WooEasyLife হোম',
            'anchor_hints' => ['WooEasyLife', 'WooCommerce অপারেশন প্ল্যাটফর্ম'],
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
        'tone' => 'বাস্তবসম্মত, সহজ বাংলা, সেলার-টক — কর্পোরেট US ব্লগ নয়',
        'founder' => 'Muhibbullah Ansary, Developer of WooEasyLife',
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
        'weights' => [
            'opportunity' => 15,
            'outline' => 15,
            'seo' => 30,
            'content' => 25,
            'image' => 15,
        ],
        'progress' => [
            'intake' => 5,
            'research' => 15,
            'hooks' => 12,
            'outline' => 15,
            'draft' => 28,
            'image' => 20,
            'finalize' => 5,
        ],
    ],
];
