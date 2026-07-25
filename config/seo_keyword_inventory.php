<?php

/**
 * WooEasyLife SEO keyword / slug inventory (source of truth for mentors + Blog AI).
 *
 * Rules:
 * - One indexable URL per distinct intent (synonyms expand the same URL).
 * - type=money|tool|pillar → already live commercial pages (blogs must soft-link, never steal head term).
 * - type=planned_faq → build under /faq after hub exists.
 * - type=planned_blog → Blog AI preferred topics (long-tail, not money head terms).
 * - type=planned_commercial → later feature landings (Shipped features only).
 * - status=live|planned|hold.
 *
 * Canonical CTAs always https://app.wpsalehub.com{path}
 *
 * Mentored strategy: .cursor/skills/seo-mentor/SKILL.md
 */

return [
    'version' => '2026-07-25',
    'domain' => 'https://app.wpsalehub.com',

    /*
    |--------------------------------------------------------------------------
    | Money / tool / pillar pages (LIVE) — do not create blog duplicates
    |--------------------------------------------------------------------------
    */
    'entries' => [
        // —— Fraud / checker cluster ——
        [
            'cluster' => 'fraud_checker',
            'type' => 'money',
            'status' => 'live',
            'path' => '/bd-fraud-checker',
            'slug' => 'bd-fraud-checker',
            'primary' => 'fraud checker bd',
            'secondary' => [
                'free fraud checker bd',
                'courier fraud checker',
                'fraud check bangladesh',
                'delivery fraud checker bd',
                'fraud checker bd phone number',
                'fraud checker bd steadfast',
                'fraud checker bd pathao',
                'ফ্রড চেকার',
                'ফ্রড চেকার বিডি',
                'কুরিয়ার ফ্রড চেকার',
            ],
            'cta' => '/bd-fraud-checker',
            'notes' => 'SERP head term; P0: SSR checker above fold + WebApplication/HowTo schema',
        ],
        [
            'cluster' => 'fraud_checker',
            'type' => 'pillar',
            'status' => 'live',
            'path' => '/fake-customer-check',
            'slug' => 'fake-customer-check',
            'primary' => 'fake customer check bd',
            'secondary' => [
                'fraud customer checker',
                'ফ্রড কাস্টমার চেকার',
                'customer delivery history',
                'মোবাইল নম্বর দিয়ে কাস্টমার চেক',
            ],
            'cta' => '/fake-customer-check',
        ],
        [
            'cluster' => 'fraud_checker',
            'type' => 'tool',
            'status' => 'live',
            'path' => '/bd-courier-ratio-checker',
            'slug' => 'bd-courier-ratio-checker',
            'primary' => 'courier ratio checker',
            'secondary' => [
                'courier success rate check',
                'delivery ratio check',
                'customer return ratio',
                'সাকসেস রেট চেক',
            ],
            'cta' => '/bd-courier-ratio-checker',
        ],
        [
            'cluster' => 'fraud_checker',
            'type' => 'pillar',
            'status' => 'live',
            'path' => '/pathao-fraud-check',
            'slug' => 'pathao-fraud-check',
            'primary' => 'pathao fraud check',
            'secondary' => ['pathao customer history', 'pathao delivery success rate', 'pathao হিস্টোরি'],
            'cta' => '/pathao-fraud-check',
        ],
        [
            'cluster' => 'fraud_checker',
            'type' => 'pillar',
            'status' => 'live',
            'path' => '/steadfast-fraud-check',
            'slug' => 'steadfast-fraud-check',
            'primary' => 'steadfast fraud check',
            'secondary' => ['steadfast customer check', 'steadfast courier ratio', 'steadfast হিস্টোরি'],
            'cta' => '/steadfast-fraud-check',
        ],
        [
            'cluster' => 'fraud_checker',
            'type' => 'pillar',
            'status' => 'live',
            'path' => '/redx-fraud-check',
            'slug' => 'redx-fraud-check',
            'primary' => 'redx fraud check',
            'secondary' => ['redx customer history', 'redx success rate', 'redx হিস্টোরি'],
            'cta' => '/redx-fraud-check',
        ],
        [
            'cluster' => 'fraud_checker',
            'type' => 'pillar',
            'status' => 'live',
            'path' => '/fraudbd-alternative',
            'slug' => 'fraudbd-alternative',
            'primary' => 'fraudbd alternative',
            'secondary' => ['fraudbd alternative bangladesh', 'fraudbd এর বিকল্প'],
            'cta' => '/bd-fraud-checker',
        ],

        // —— Fake order / protection ——
        [
            'cluster' => 'fake_order',
            'type' => 'money',
            'status' => 'live',
            'path' => '/fake-order-protection',
            'slug' => 'fake-order-protection',
            'primary' => 'woocommerce fake order protection',
            'secondary' => [
                'fake cod order blocker',
                'cod fraud prevention',
                'কিভাবে ফেক অর্ডার আটকাবো',
                'চেকআউট OTP',
            ],
            'cta' => '/fake-order-protection',
        ],
        [
            'cluster' => 'fake_order',
            'type' => 'pillar',
            'status' => 'live',
            'path' => '/ki-vabe-fake-order-atkabo',
            'slug' => 'ki-vabe-fake-order-atkabo',
            'primary' => 'কিভাবে ফেক অর্ডার আটকাবো',
            'secondary' => ['fake order check', 'ফেক অর্ডার চেক', 'ki vabe fake order atkabo'],
            'cta' => '/fake-order-protection',
        ],
        [
            'cluster' => 'checkout_protection',
            'type' => 'pillar',
            'status' => 'live',
            'path' => '/customer-verification',
            'slug' => 'customer-verification',
            'primary' => 'customer verification bangladesh',
            'secondary' => ['woocommerce checkout otp', 'cod verification'],
            'cta' => '/fake-order-protection',
        ],

        // —— Return / COD loss ——
        [
            'cluster' => 'return_loss',
            'type' => 'tool',
            'status' => 'live',
            'path' => '/return-loss-calculator',
            'slug' => 'return-loss-calculator',
            'primary' => 'return loss calculator',
            'secondary' => ['cod return cost calculator', 'courier return loss', 'রিটার্ন লস ক্যালকুলেটর', 'মাসিক রিটার্ন লস'],
            'cta' => '/return-loss-calculator',
        ],
        [
            'cluster' => 'cod',
            'type' => 'pillar',
            'status' => 'live',
            'path' => '/cod-return-reduction',
            'slug' => 'cod-return-reduction',
            'primary' => 'reduce cod return bangladesh',
            'secondary' => ['cod rto reduction', 'parcel return কমানোর উপায়', 'cod return rate'],
            'cta' => '/cod-return-reduction',
        ],

        // —— Courier ops ——
        [
            'cluster' => 'courier',
            'type' => 'money',
            'status' => 'live',
            'path' => '/courier-auto-entry',
            'slug' => 'courier-auto-entry',
            'primary' => 'woocommerce courier integration bangladesh',
            'secondary' => [
                'courier auto entry',
                'কুরিয়ার অটো এন্ট্রি',
                'pathao steadfast redx auto',
                'courier status sync',
            ],
            'cta' => '/courier-auto-entry',
        ],
        [
            'cluster' => 'courier',
            'type' => 'pillar',
            'status' => 'live',
            'path' => '/steadfast-integration',
            'slug' => 'steadfast-integration',
            'primary' => 'steadfast woocommerce plugin',
            'secondary' => ['steadfast wordpress plugin', 'steadfast integration'],
            'cta' => '/steadfast-integration',
        ],
        [
            'cluster' => 'courier',
            'type' => 'pillar',
            'status' => 'live',
            'path' => '/pathao-courier-guide',
            'slug' => 'pathao-courier-guide',
            'primary' => 'pathao woocommerce plugin',
            'secondary' => ['pathao courier api', 'pathao wordpress plugin'],
            'cta' => '/pathao-courier-guide',
        ],
        [
            'cluster' => 'courier',
            'type' => 'pillar',
            'status' => 'live',
            'path' => '/redx-courier-guide',
            'slug' => 'redx-courier-guide',
            'primary' => 'redx woocommerce plugin',
            'secondary' => ['redx wordpress plugin', 'redx courier api'],
            'cta' => '/redx-courier-guide',
        ],
        [
            'cluster' => 'courier_charge',
            'type' => 'tool',
            'status' => 'live',
            'path' => '/courier-charge-calculator',
            'slug' => 'courier-charge-calculator',
            'primary' => 'কুরিয়ার চার্জ ক্যালকুলেটর',
            'secondary' => ['pathao চার্জ', 'steadfast প্রাইসিং', 'redx ডেলিভারি চার্জ'],
            'cta' => '/courier-charge-calculator',
        ],

        // —— Ads ——
        [
            'cluster' => 'facebook_ads',
            'type' => 'tool',
            'status' => 'live',
            'path' => '/ads-roas-calculator',
            'slug' => 'ads-roas-calculator',
            'primary' => 'facebook ads roas calculator',
            'secondary' => ['ফেক purchase', 'পিক্সেল roas', 'অ্যাড বাজেট নষ্ট'],
            'cta' => '/ads-roas-calculator',
        ],

        /*
        |--------------------------------------------------------------------------
        | Planned FAQ URLs (hub /faq first) — long-tail indexables
        |--------------------------------------------------------------------------
        */
        [
            'cluster' => 'fraud_checker',
            'type' => 'planned_faq',
            'status' => 'planned',
            'path' => '/faq/courier-success-rate-kivabe-bujhbo',
            'slug' => 'courier-success-rate-kivabe-bujhbo',
            'primary' => 'courier success rate কীভাবে বুঝব',
            'secondary' => [
                'customer delivery ratio কত হলে ভালো',
                'courier return rate কীভাবে বুঝব',
                'delivered vs cancelled ratio',
            ],
            'cta' => '/bd-courier-ratio-checker',
            'notes' => 'After /faq hub exists',
        ],
        [
            'cluster' => 'fraud_checker',
            'type' => 'planned_faq',
            'status' => 'planned',
            'path' => '/faq/success-rate-kom-hole-ki-korbo',
            'slug' => 'success-rate-kom-hole-ki-korbo',
            'primary' => 'success rate কম হলে কী করব',
            'secondary' => [
                'low courier success rate customer',
                'risky cod order confirm করব কি',
                'customer return বেশি হলে কী করবেন',
            ],
            'cta' => '/bd-fraud-checker',
        ],
        [
            'cluster' => 'checkout_protection',
            'type' => 'planned_faq',
            'status' => 'planned',
            'path' => '/faq/cod-order-otp-kokhon',
            'slug' => 'cod-order-otp-kokhon',
            'primary' => 'cod order otp কখন',
            'secondary' => [
                'woocommerce cod otp verification',
                'fake order otp plugin bangladesh',
                'checkout phone verification bangladesh',
            ],
            'cta' => '/fake-order-protection',
        ],
        [
            'cluster' => 'checkout_protection',
            'type' => 'planned_faq',
            'status' => 'planned',
            'path' => '/faq/woocommerce-customer-blacklist',
            'slug' => 'woocommerce-customer-blacklist',
            'primary' => 'woocommerce customer blacklist',
            'secondary' => [
                'fake customer phone number block',
                'cod customer blacklist plugin',
                'email ip device block woocommerce',
            ],
            'cta' => '/fake-order-protection',
        ],
        [
            'cluster' => 'fake_order',
            'type' => 'planned_faq',
            'status' => 'planned',
            'path' => '/faq/duplicate-cod-order-block',
            'slug' => 'duplicate-cod-order-block',
            'primary' => 'duplicate cod order block',
            'secondary' => [
                'same customer multiple cod order',
                'repeat fake order block',
                'woocommerce spam order prevention',
            ],
            'cta' => '/fake-order-protection',
        ],
        [
            'cluster' => 'fraud_checker',
            'type' => 'planned_faq',
            'status' => 'planned',
            'path' => '/faq/customer-delivery-history-check',
            'slug' => 'customer-delivery-history-check',
            'primary' => 'customer delivery history check',
            'secondary' => [
                'phone number দিয়ে courier history',
                'pathao steadfast redx history check',
                'order confirm করার আগে customer check',
            ],
            'cta' => '/bd-fraud-checker',
        ],
        [
            'cluster' => 'fraud_checker',
            'type' => 'planned_faq',
            'status' => 'planned',
            'path' => '/faq/customer-fraud-score-ki',
            'slug' => 'customer-fraud-score-ki',
            'primary' => 'customer fraud score কী',
            'secondary' => [
                'courier risk score meaning',
                'fraud score কীভাবে হিসাব হয়',
                'customer behavior score woocommerce',
            ],
            'cta' => '/fake-customer-check',
        ],
        [
            'cluster' => 'return_loss',
            'type' => 'planned_faq',
            'status' => 'planned',
            'path' => '/faq/cod-return-loss-hisab',
            'slug' => 'cod-return-loss-hisab',
            'primary' => 'cod return loss হিসাব',
            'secondary' => [
                'parcel return cost calculation',
                'courier return charge loss',
                'rto loss formula bangladesh',
            ],
            'cta' => '/return-loss-calculator',
        ],

        /*
        |--------------------------------------------------------------------------
        | Planned blog topics (Blog AI — long-tail, not money head terms)
        |--------------------------------------------------------------------------
        */
        [
            'cluster' => 'fraud_checker',
            'type' => 'planned_blog',
            'status' => 'planned',
            'path' => '/blog/success-rate-kom-hole-order-confirm',
            'slug' => 'success-rate-kom-hole-order-confirm',
            'primary' => 'সাকসেস রেট কম হলে অর্ডার কনফার্ম করব কি',
            'secondary' => ['risky customer confirm করার আগে', 'courier ratio দেখে সিদ্ধান্ত'],
            'cta' => '/bd-fraud-checker',
            'article_type' => 'howto',
        ],
        [
            'cluster' => 'checkout_protection',
            'type' => 'planned_blog',
            'status' => 'planned',
            'path' => '/blog/cod-otp-kokhon-chalabo',
            'slug' => 'cod-otp-kokhon-chalabo',
            'primary' => 'COD অর্ডারে OTP কখন চালাব',
            'secondary' => ['high risk order otp', 'checkout otp bangladesh seller'],
            'cta' => '/fake-order-protection',
            'article_type' => 'howto',
        ],
        [
            'cluster' => 'checkout_protection',
            'type' => 'planned_blog',
            'status' => 'live',
            'path' => '/blog/blacklist-customer-after-returns',
            'slug' => 'blacklist-customer-after-returns',
            'primary' => 'রিটার্ন বেশি কাস্টমার ব্ল্যাকলিস্ট',
            'secondary' => ['woocommerce customer blacklist', 'repeat returner block'],
            'cta' => '/fake-order-protection',
            'article_type' => 'howto',
            'notes' => 'Live (200). Soft-link from fraud pillars; refresh if thin — do not create duplicate',
        ],
        [
            'cluster' => 'missing_order',
            'type' => 'planned_blog',
            'status' => 'planned',
            'path' => '/blog/woocommerce-abandoned-checkout-recovery-bd',
            'slug' => 'woocommerce-abandoned-checkout-recovery-bd',
            'primary' => 'woocommerce abandoned checkout recovery bangladesh',
            'secondary' => ['হারানো অর্ডার ফেরত', 'missing order wooCommerce', 'incomplete checkout recovery'],
            'cta' => '/pricing',
            'article_type' => 'howto',
            'notes' => 'Feature: Missing Orders (Shipped)',
        ],
        [
            'cluster' => 'courier',
            'type' => 'planned_blog',
            'status' => 'planned',
            'path' => '/blog/steadfast-pathao-redx-auto-entry-time-save',
            'slug' => 'steadfast-pathao-redx-auto-entry-time-save',
            'primary' => 'কুরিয়ার অটো এন্ট্রিতে কত সময় বাঁচে',
            'secondary' => ['manual parcel entry vs auto', 'woocommerce courier webhook'],
            'cta' => '/courier-auto-entry',
            'article_type' => 'case_study',
        ],
        [
            'cluster' => 'return_loss',
            'type' => 'planned_blog',
            'status' => 'planned',
            'path' => '/blog/cod-return-loss-formula-bangladesh',
            'slug' => 'cod-return-loss-formula-bangladesh',
            'primary' => 'COD রিটার্ন লস কীভাবে হিসাব করব',
            'secondary' => ['rto cost formula', 'return charge + product loss'],
            'cta' => '/return-loss-calculator',
            'article_type' => 'howto',
        ],

        /*
        |--------------------------------------------------------------------------
        | Planned commercial feature landings (after fraud Phase 1)
        |--------------------------------------------------------------------------
        */
        [
            'cluster' => 'missing_order',
            'type' => 'planned_commercial',
            'status' => 'planned',
            'path' => '/woocommerce-abandoned-checkout-recovery-bangladesh',
            'slug' => 'woocommerce-abandoned-checkout-recovery-bangladesh',
            'primary' => 'woocommerce abandoned checkout recovery bangladesh',
            'secondary' => ['missing orders wooCommerce', 'হারানো অর্ডার রিকভারি'],
            'cta' => '/pricing',
            'notes' => 'Shipped: missing_orders — full guide landing, not thin FAQ',
        ],
        [
            'cluster' => 'checkout_protection',
            'type' => 'planned_commercial',
            'status' => 'planned',
            'path' => '/woocommerce-cod-otp-verification',
            'slug' => 'woocommerce-cod-otp-verification',
            'primary' => 'woocommerce cod otp plugin bangladesh',
            'secondary' => ['checkout otp verification', 'cod otp verification'],
            'cta' => '/fake-order-protection',
            'notes' => 'Shipped: checkout OTP',
        ],
        [
            'cluster' => 'checkout_protection',
            'type' => 'planned_commercial',
            'status' => 'planned',
            'path' => '/woocommerce-customer-blacklist',
            'slug' => 'woocommerce-customer-blacklist',
            'primary' => 'woocommerce customer blacklist plugin',
            'secondary' => ['phone email ip device block'],
            'cta' => '/fake-order-protection',
            'notes' => 'Shipped: customer_blacklist',
        ],
        [
            'cluster' => 'checkout_protection',
            'type' => 'planned_commercial',
            'status' => 'planned',
            'path' => '/woocommerce-duplicate-order-blocker',
            'slug' => 'woocommerce-duplicate-order-blocker',
            'primary' => 'woocommerce duplicate order blocker',
            'secondary' => ['same cart duplicate block', 'repeat order block'],
            'cta' => '/fake-order-protection',
            'notes' => 'Shipped: duplicate same-cart block',
        ],
        [
            'cluster' => 'ai_orders',
            'type' => 'planned_commercial',
            'status' => 'planned',
            'path' => '/woocommerce-order-from-image',
            'slug' => 'woocommerce-order-from-image',
            'primary' => 'ai order from image woocommerce',
            'secondary' => ['screenshot থেকে অর্ডার', 'image to order bangladesh'],
            'cta' => '/pricing',
            'notes' => 'Shipped: AI Order from Image',
        ],
        [
            'cluster' => 'ai_orders',
            'type' => 'planned_commercial',
            'status' => 'planned',
            'path' => '/woocommerce-order-from-text',
            'slug' => 'woocommerce-order-from-text',
            'primary' => 'create woocommerce order from text',
            'secondary' => ['মেসেজ থেকে অর্ডার', 'ai order from text'],
            'cta' => '/pricing',
            'notes' => 'Shipped: AI Order from Text',
        ],
        [
            'cluster' => 'ai_orders',
            'type' => 'planned_commercial',
            'status' => 'planned',
            'path' => '/woocommerce-address-fix-ai',
            'slug' => 'woocommerce-address-fix-ai',
            'primary' => 'woocommerce address correction ai',
            'secondary' => ['address fix with ai', 'ঠিকানা ঠিক করা ai'],
            'cta' => '/pricing',
            'notes' => 'Shipped: address Fix with AI',
        ],
        [
            'cluster' => 'fraud_checker',
            'type' => 'planned_commercial',
            'status' => 'planned',
            'path' => '/woocommerce-customer-delivery-history',
            'slug' => 'woocommerce-customer-delivery-history',
            'primary' => 'customer delivery history woocommerce',
            'secondary' => ['order page courier history', 'delivery history on order'],
            'cta' => '/bd-fraud-checker',
            'notes' => 'Shipped: customer_delivery_history',
        ],
    ],
];
