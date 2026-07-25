<?php

/**
 * FAQ hub + question page meta (BN first).
 * Slugs must match config/seo_keyword_inventory.php planned_faq rows.
 */

$questionLinks = [
    ['path' => '/faq/courier-success-rate-kivabe-bujhbo', 'label' => 'কুরিয়ার সাকসেস রেট কীভাবে বুঝব'],
    ['path' => '/faq/success-rate-kom-hole-ki-korbo', 'label' => 'সাকসেস রেট কম হলে কী করব'],
    ['path' => '/faq/customer-delivery-history-check', 'label' => 'কাস্টমার ডেলিভারি হিস্টোরি চেক'],
    ['path' => '/faq/customer-fraud-score-ki', 'label' => 'কাস্টমার ফ্রড স্কোর কী'],
    ['path' => '/faq/cod-order-otp-kokhon', 'label' => 'COD অর্ডারে OTP কখন'],
    ['path' => '/faq/woocommerce-customer-blacklist', 'label' => 'WooCommerce কাস্টমার ব্ল্যাকলিস্ট'],
    ['path' => '/faq/duplicate-cod-order-block', 'label' => 'ডুপ্লিকেট COD অর্ডার ব্লক'],
    ['path' => '/faq/cod-return-loss-hisab', 'label' => 'COD রিটার্ন লস হিসাব'],
];

$toolLinks = [
    ['path' => '/bd-fraud-checker', 'label' => 'ফ্রি ফ্রড চেকার'],
    ['path' => '/fake-order-protection', 'label' => 'ফেক অর্ডার প্রোটেকশন'],
    ['path' => '/return-loss-calculator', 'label' => 'রিটার্ন লস ক্যালকুলেটর'],
    ['path' => '/courier-auto-entry', 'label' => 'কুরিয়ার অটো এন্ট্রি'],
    ['path' => '/pricing', 'label' => 'প্রাইসিং / ট্রায়াল'],
];

return [
    'faq' => [
        'title' => 'FAQ — ফ্রড চেক, OTP, ব্ল্যাকলিস্ট ও COD রিটার্ন | WooEasyLife',
        'description' => 'WooEasyLife FAQ: কুরিয়ার সাকসেস রেট, ফ্রড স্কোর, COD OTP, কাস্টমার ব্ল্যাকলিস্ট, ডুপ্লিকেট অর্ডার ব্লক ও রিটার্ন লস হিসাব—BD WooCommerce সেলারদের জন্য।',
        'canonical_path' => '/faq',
        'og_type' => 'website',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'WooEasyLife FAQ — ফ্রড, OTP ও COD প্রশ্নোত্তর',
        'prerender_lead' => 'ফেক অর্ডার, কুরিয়ার হিস্টোরি, চেকআউট OTP, ব্ল্যাকলিস্ট ও রিটার্ন লস নিয়ে সবচেয়ে বেশি জিজ্ঞাসিত প্রশ্ন—সরাসরি উত্তর + টুল লিংক।',
        'page_kind' => 'faq_hub',
        'hreflang_paths' => [
            'bn-BD' => '/faq',
            'x-default' => '/faq',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
        ],
        'cluster_links' => array_merge($questionLinks, $toolLinks),
        'faqs' => [
            [
                'q' => 'কুরিয়ার সাকসেস রেট কীভাবে বুঝব?',
                'a' => 'ডেলিভার্ড বনাম ক্যানসেল/রিটার্ন রেশিও দেখে। বিস্তারিত: /faq/courier-success-rate-kivabe-bujhbo — টুল: /bd-courier-ratio-checker',
            ],
            [
                'q' => 'সাকসেস রেট কম হলে কী করব?',
                'a' => 'অন্ধ শিপ নয়—কল/OTP/হোল্ড। গাইড: /faq/success-rate-kom-hole-ki-korbo — চেক: /bd-fraud-checker',
            ],
            [
                'q' => 'COD অর্ডারে OTP কখন নেব?',
                'a' => 'ঝুঁকিপূর্ণ নম্বর বা নতুন কাস্টমারে চেকআউট OTP। বিস্তারিত: /faq/cod-order-otp-kokhon — সেটআপ: /fake-order-protection',
            ],
            [
                'q' => 'WooCommerce-এ কাস্টমার ব্ল্যাকলিস্ট কীভাবে?',
                'a' => 'ফোন/ইমেইল/IP/ডিভাইস ব্লক। গাইড: /faq/woocommerce-customer-blacklist',
            ],
            [
                'q' => 'ডুপ্লিকেট COD অর্ডার কীভাবে আটকাব?',
                'a' => 'একই কার্ট/নম্বর পুনরাবৃত্তি ব্লক। /faq/duplicate-cod-order-block',
            ],
            [
                'q' => 'কাস্টমার ডেলিভারি হিস্টোরি কীভাবে চেক করব?',
                'a' => 'মোবাইল নম্বর দিয়ে Pathao/Steadfast/RedX হিস্টোরি। /faq/customer-delivery-history-check',
            ],
            [
                'q' => 'কাস্টমার ফ্রড স্কোর কী?',
                'a' => 'বিহেভিয়ার সিগন্যাল—ভেরডিক্ট নয়। /faq/customer-fraud-score-ki — /fake-customer-check',
            ],
            [
                'q' => 'COD রিটার্ন লস কীভাবে হিসাব করব?',
                'a' => 'দৈনিক অর্ডার × রিটার্ন রেট × প্রতি রিটার্ন খরচ। /faq/cod-return-loss-hisab — /return-loss-calculator',
            ],
        ],
    ],

    'faq_courier_success_rate_kivabe_bujhbo' => [
        'title' => 'কুরিয়ার সাকসেস রেট কীভাবে বুঝব — COD গাইড | WooEasyLife',
        'description' => 'কুরিয়ার সাকসেস রেট কীভাবে বুঝবেন: delivered vs cancelled/return ratio, BD COD-তে কত হলে ভালো, এবং অর্ডার কনফার্মের আগে কী দেখবেন।',
        'canonical_path' => '/faq/courier-success-rate-kivabe-bujhbo',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'কুরিয়ার সাকসেস রেট কীভাবে বুঝব?',
        'prerender_lead' => 'ডেলিভার্ড বনাম রিটার্ন/ক্যানসেল রেশিও পড়ে COD কনফার্মের আগে ঝুঁকি বোঝার সহজ নিয়ম।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/bd-courier-ratio-checker',
        'hreflang_paths' => [
            'bn-BD' => '/faq/courier-success-rate-kivabe-bujhbo',
            'x-default' => '/faq/courier-success-rate-kivabe-bujhbo',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'সাকসেস রেট বোঝা', 'path' => '/faq/courier-success-rate-kivabe-bujhbo'],
        ],
        'cluster_links' => [
            ['path' => '/faq/success-rate-kom-hole-ki-korbo', 'label' => 'রেট কম হলে কী করব'],
            ['path' => '/faq/customer-delivery-history-check', 'label' => 'ডেলিভারি হিস্টোরি চেক'],
            ['path' => '/bd-courier-ratio-checker', 'label' => 'Ratio Checker'],
            ['path' => '/bd-fraud-checker', 'label' => 'ফ্রড চেকার'],
            ['path' => '/faq', 'label' => 'সব FAQ'],
        ],
        'faqs' => [
            [
                'q' => 'সাকসেস রেট কত হলে সাধারণত নিরাপদ?',
                'a' => 'উচ্চ delivered অনুপাত (যেমন ৭০%+) সাধারণত সবুজ জোন—তবু নতুন নম্বর/ঠিকানায় ছোট কল ভালো। কম রেটে: /faq/success-rate-kom-hole-ki-korbo',
            ],
            [
                'q' => 'শুধু এক কুরিয়ার দেখলেই কি যথেষ্ট?',
                'a' => 'না—Pathao, Steadfast, RedX একসাথে দেখলে প্যাটার্ন স্পষ্ট হয়। টুল: /bd-fraud-checker',
            ],
            [
                'q' => 'রেট ও স্কোর কি একই জিনিস?',
                'a' => 'না। রেট = ডেলিভারি হিস্টোরি অনুপাত; স্কোর = বিহেভিয়ার সিগন্যাল। স্কোর: /faq/customer-fraud-score-ki',
            ],
        ],
    ],

    'faq_success_rate_kom_hole_ki_korbo' => [
        'title' => 'সাকসেস রেট কম হলে কী করব — COD সিদ্ধান্ত | WooEasyLife',
        'description' => 'কাস্টমারের কুরিয়ার সাকসেস রেট কম হলে কী করবেন: কল, OTP, হোল্ড, অগ্রিম চার্জ নাকি বাতিল—BD COD সেলারদের জন্য স্পষ্ট SOP।',
        'canonical_path' => '/faq/success-rate-kom-hole-ki-korbo',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'সাকসেস রেট কম হলে কী করব?',
        'prerender_lead' => 'লাল/হলুদ জোনে অন্ধ শিপ নয়—কল, OTP বা হোল্ড দিয়ে রিটার্ন ৳ বাঁচান।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/bd-fraud-checker',
        'hreflang_paths' => [
            'bn-BD' => '/faq/success-rate-kom-hole-ki-korbo',
            'x-default' => '/faq/success-rate-kom-hole-ki-korbo',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'রেট কম হলে', 'path' => '/faq/success-rate-kom-hole-ki-korbo'],
        ],
        'cluster_links' => [
            ['path' => '/faq/courier-success-rate-kivabe-bujhbo', 'label' => 'রেট কীভাবে বুঝব'],
            ['path' => '/faq/cod-order-otp-kokhon', 'label' => 'OTP কখন'],
            ['path' => '/bd-fraud-checker', 'label' => 'এখনই চেক করুন'],
            ['path' => '/fake-order-protection', 'label' => 'প্রোটেকশন'],
            ['path' => '/faq', 'label' => 'সব FAQ'],
        ],
        'faqs' => [
            [
                'q' => 'কম রেট মানেই কি ফেক কাস্টমার?',
                'a' => 'না—একটা খারাপ রেকর্ড সবসময় ফেক নয়। হিস্টোরি + ছোট কল একসাথে নিরাপদ। চেক: /bd-fraud-checker',
            ],
            [
                'q' => 'বারবার কম রেট হলে কী করব?',
                'a' => 'ব্ল্যাকলিস্ট ও OTP চালু রাখুন। /faq/woocommerce-customer-blacklist',
            ],
            [
                'q' => 'রিটার্ন লস কত হচ্ছে কীভাবে দেখব?',
                'a' => '/faq/cod-return-loss-hisab এবং /return-loss-calculator',
            ],
        ],
    ],

    'faq_cod_order_otp_kokhon' => [
        'title' => 'COD অর্ডারে OTP কখন নেব — চেকআউট ভেরিফিকেশন | WooEasyLife',
        'description' => 'COD অর্ডারে OTP কখন লাগবে: নতুন নম্বর, কম সাকসেস রেট, সন্দেহজনক অর্ডার—WooCommerce চেকআউট OTP দিয়ে ফেক অর্ডার কমান।',
        'canonical_path' => '/faq/cod-order-otp-kokhon',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'COD অর্ডারে OTP কখন নেব?',
        'prerender_lead' => 'সব অর্ডারে নয়—ঝুঁকিপূর্ণ নম্বর ও নতুন কাস্টমারে চেকআউট OTP সবচেয়ে কার্যকর।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/fake-order-protection',
        'hreflang_paths' => [
            'bn-BD' => '/faq/cod-order-otp-kokhon',
            'x-default' => '/faq/cod-order-otp-kokhon',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'COD OTP', 'path' => '/faq/cod-order-otp-kokhon'],
        ],
        'cluster_links' => [
            ['path' => '/faq/woocommerce-customer-blacklist', 'label' => 'ব্ল্যাকলিস্ট'],
            ['path' => '/faq/duplicate-cod-order-block', 'label' => 'ডুপ্লিকেট ব্লক'],
            ['path' => '/fake-order-protection', 'label' => 'ফেক অর্ডার প্রোটেকশন'],
            ['path' => '/customer-verification', 'label' => 'কাস্টমার ভেরিফিকেশন'],
            ['path' => '/faq', 'label' => 'সব FAQ'],
        ],
        'faqs' => [
            [
                'q' => 'সব COD-তে OTP কি লাগবে?',
                'a' => 'প্রয়োজন নেই—উচ্চ সাকসেস/পুরনো কাস্টমারে ফ্রিকশন বাড়ে। ঝুঁকি জোনে চালু রাখুন। সেটআপ: /fake-order-protection',
            ],
            [
                'q' => 'OTP ছাড়া আর কী লাগে?',
                'a' => 'ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট। /faq/duplicate-cod-order-block',
            ],
            [
                'q' => 'মোবাইল অ্যাপে কি approve করা যায়?',
                'a' => 'হ্যাঁ—পুশ ও কল ম্যাচসহ। /woocommerce-mobile-app',
            ],
        ],
    ],

    'faq_woocommerce_customer_blacklist' => [
        'title' => 'WooCommerce কাস্টমার ব্ল্যাকলিস্ট কীভাবে — ফোন/IP ব্লক | WooEasyLife',
        'description' => 'WooCommerce-এ ফেক কাস্টমার ব্ল্যাকলিস্ট: ফোন, ইমেইল, IP, ডিভাইস ব্লক করে বারবার COD রিটার্ন ও স্প্যাম অর্ডার আটকান।',
        'canonical_path' => '/faq/woocommerce-customer-blacklist',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'WooCommerce কাস্টমার ব্ল্যাকলিস্ট কীভাবে করবেন?',
        'prerender_lead' => 'বারবার রিটার্ন/ফেক নম্বর একবার ব্লক করলে একই ক্ষতি বারবার হয় না।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/fake-order-protection',
        'hreflang_paths' => [
            'bn-BD' => '/faq/woocommerce-customer-blacklist',
            'x-default' => '/faq/woocommerce-customer-blacklist',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'ব্ল্যাকলিস্ট', 'path' => '/faq/woocommerce-customer-blacklist'],
        ],
        'cluster_links' => [
            ['path' => '/faq/cod-order-otp-kokhon', 'label' => 'OTP কখন'],
            ['path' => '/faq/duplicate-cod-order-block', 'label' => 'ডুপ্লিকেট ব্লক'],
            ['path' => '/fake-order-protection', 'label' => 'প্রোটেকশন সেটআপ'],
            ['path' => '/blog/blacklist-customer-after-returns', 'label' => 'রিটার্নের পর ব্ল্যাকলিস্ট'],
            ['path' => '/faq', 'label' => 'সব FAQ'],
        ],
        'faqs' => [
            [
                'q' => 'শুধু ফোন ব্লকই কি যথেষ্ট?',
                'a' => 'না—ইমেইল/IP/ডিভাইসও বদলে আসে। /fake-order-protection-এ মাল্টি-সিগন্যাল ব্লক আছে।',
            ],
            [
                'q' => 'কুরিয়ার কি আমার হয়ে ব্লক করে?',
                'a' => 'সাধারণত না—স্টোর-লেভেল ব্ল্যাকলিস্ট আপনার দায়িত্ব। ব্লগ: /blog/blacklist-customer-after-returns',
            ],
            [
                'q' => 'চেকের পর কখন ব্লক করব?',
                'a' => 'বারবার লাল জোন/ফেক প্যাটার্ন নিশ্চিত হলে। আগে চেক: /bd-fraud-checker',
            ],
        ],
    ],

    'faq_duplicate_cod_order_block' => [
        'title' => 'ডুপ্লিকেট COD অর্ডার ব্লক — একই কাস্টমার স্প্যাম আটকান | WooEasyLife',
        'description' => 'একই কাস্টমারের একাধিক COD/স্প্যাম অর্ডার কীভাবে ব্লক করবেন: ডুপ্লিকেট কার্ট ব্লক, দৈনিক লিমিট ও ফেক অর্ডার প্রোটেকশন।',
        'canonical_path' => '/faq/duplicate-cod-order-block',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'ডুপ্লিকেট COD অর্ডার কীভাবে ব্লক করবেন?',
        'prerender_lead' => 'একই কার্ট/নম্বর দিয়ে বারবার অর্ডার—প্যাকিং ও কুরিয়ার ফি নষ্ট করার আগেই আটকান।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/fake-order-protection',
        'hreflang_paths' => [
            'bn-BD' => '/faq/duplicate-cod-order-block',
            'x-default' => '/faq/duplicate-cod-order-block',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'ডুপ্লিকেট ব্লক', 'path' => '/faq/duplicate-cod-order-block'],
        ],
        'cluster_links' => [
            ['path' => '/faq/cod-order-otp-kokhon', 'label' => 'OTP কখন'],
            ['path' => '/faq/woocommerce-customer-blacklist', 'label' => 'ব্ল্যাকলিস্ট'],
            ['path' => '/fake-order-protection', 'label' => 'প্রোটেকশন'],
            ['path' => '/ki-vabe-fake-order-atkabo', 'label' => 'ফেক অর্ডার গাইড'],
            ['path' => '/faq', 'label' => 'সব FAQ'],
        ],
        'faqs' => [
            [
                'q' => 'ডুপ্লিকেট ব্লক কি বৈধ অর্ডারও আটকায়?',
                'a' => 'রুল সঠিক সেট করলে একই কার্টের স্প্যাম আটকে; আলাদা আইটেম/নতুন অর্ডার পাস করতে পারে। সেটআপ: /fake-order-protection',
            ],
            [
                'q' => 'দৈনিক লিমিট কী?',
                'a' => 'এক নম্বরে দিনে কত অর্ডার—স্প্যাম কমায়। /fake-order-protection',
            ],
            [
                'q' => 'চেকআউট ছাড়াও কি লাগে?',
                'a' => 'কনফার্মের আগে হিস্টোরি চেক। /faq/customer-delivery-history-check',
            ],
        ],
    ],

    'faq_customer_delivery_history_check' => [
        'title' => 'কাস্টমার ডেলিভারি হিস্টোরি চেক — ফোন নম্বরে | WooEasyLife',
        'description' => 'মোবাইল নম্বর দিয়ে Pathao, Steadfast, RedX ডেলিভারি হিস্টোরি চেক করে COD কনফার্মের আগে কাস্টমার যাচাই করুন।',
        'canonical_path' => '/faq/customer-delivery-history-check',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'কাস্টমার ডেলিভারি হিস্টোরি কীভাবে চেক করবেন?',
        'prerender_lead' => 'অর্ডার কনফার্মের আগে ফোন নম্বর দিয়ে কুরিয়ার হিস্টোরি দেখুন—ফ্রি টুল, অ্যাকাউন্ট ছাড়াই।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/bd-fraud-checker',
        'hreflang_paths' => [
            'bn-BD' => '/faq/customer-delivery-history-check',
            'x-default' => '/faq/customer-delivery-history-check',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'হিস্টোরি চেক', 'path' => '/faq/customer-delivery-history-check'],
        ],
        'cluster_links' => [
            ['path' => '/faq/courier-success-rate-kivabe-bujhbo', 'label' => 'রেট বোঝা'],
            ['path' => '/faq/customer-fraud-score-ki', 'label' => 'ফ্রড স্কোর'],
            ['path' => '/bd-fraud-checker', 'label' => 'ফ্রি চেকার'],
            ['path' => '/fake-customer-check', 'label' => 'Fake Customer Check'],
            ['path' => '/faq', 'label' => 'সব FAQ'],
        ],
        'faqs' => [
            [
                'q' => 'কোন কুরিয়ার দেখা যায়?',
                'a' => 'Pathao, Steadfast, RedX। টুল: /bd-fraud-checker',
            ],
            [
                'q' => 'এটি কি ফ্রি?',
                'a' => 'ল্যান্ডিংয়ে সীমিত ফ্রি চেক—অ্যাকাউন্ট ছাড়াই। বেশি ভলিউম: /pricing',
            ],
            [
                'q' => 'চেকের পর কী করব?',
                'a' => 'সবুজ কনফার্ম; হলুদ/লালে কল/OTP। /faq/success-rate-kom-hole-ki-korbo',
            ],
        ],
    ],

    'faq_customer_fraud_score_ki' => [
        'title' => 'কাস্টমার ফ্রড স্কোর কী — অর্থ ও ব্যবহার | WooEasyLife',
        'description' => 'কাস্টমার ফ্রড/বিহেভিয়ার স্কোর কী, কীভাবে হিসাব হয়, এবং COD কনফার্মে কীভাবে ব্যবহার করবেন—ভেরডিক্ট নয়, সিগন্যাল।',
        'canonical_path' => '/faq/customer-fraud-score-ki',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'কাস্টমার ফ্রড স্কোর কী?',
        'prerender_lead' => 'স্কোর একটি ঝুঁকি সিগন্যাল—অটো-ব্লক ভেরডিক্ট নয়। হিস্টোরি ও কলের সাথে ব্যবহার করুন।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/fake-customer-check',
        'hreflang_paths' => [
            'bn-BD' => '/faq/customer-fraud-score-ki',
            'x-default' => '/faq/customer-fraud-score-ki',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'ফ্রড স্কোর', 'path' => '/faq/customer-fraud-score-ki'],
        ],
        'cluster_links' => [
            ['path' => '/faq/customer-delivery-history-check', 'label' => 'হিস্টোরি চেক'],
            ['path' => '/faq/courier-success-rate-kivabe-bujhbo', 'label' => 'সাকসেস রেট'],
            ['path' => '/fake-customer-check', 'label' => 'Fake Customer Check'],
            ['path' => '/bd-fraud-checker', 'label' => 'ফ্রড চেকার'],
            ['path' => '/faq', 'label' => 'সব FAQ'],
        ],
        'faqs' => [
            [
                'q' => 'স্কোর কি আদালতের রায়?',
                'a' => 'না—সিগন্যাল। কম স্কোরে কল/OTP নিন, অন্ধ ক্যানসেল নয়। /fake-customer-check',
            ],
            [
                'q' => 'রেট না দেখে শুধু স্কোর?',
                'a' => 'দুটোই দেখুন। রেট: /faq/courier-success-rate-kivabe-bujhbo',
            ],
            [
                'q' => 'পুনরাবৃত্তি ঝুঁকি হলে?',
                'a' => 'ব্ল্যাকলিস্ট। /faq/woocommerce-customer-blacklist',
            ],
        ],
    ],

    'faq_cod_return_loss_hisab' => [
        'title' => 'COD রিটার্ন লস হিসাব কীভাবে — RTO ফর্মুলা | WooEasyLife',
        'description' => 'COD রিটার্ন লস হিসাব: দৈনিক অর্ডার × রিটার্ন রেট × প্রতি রিটার্ন খরচ—BD সেলারদের জন্য সহজ ফর্মুলা ও ক্যালকুলেটর।',
        'canonical_path' => '/faq/cod-return-loss-hisab',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'COD রিটার্ন লস কীভাবে হিসাব করবেন?',
        'prerender_lead' => 'মাসিক ৳ লস না জানলে “কমেছে কি?” বোঝা যায় না—সহজ ফর্মুলা + ক্যালকুলেটর।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/return-loss-calculator',
        'hreflang_paths' => [
            'bn-BD' => '/faq/cod-return-loss-hisab',
            'x-default' => '/faq/cod-return-loss-hisab',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'রিটার্ন লস হিসাব', 'path' => '/faq/cod-return-loss-hisab'],
        ],
        'cluster_links' => [
            ['path' => '/return-loss-calculator', 'label' => 'রিটার্ন লস ক্যালকুলেটর'],
            ['path' => '/cod-return-reduction', 'label' => 'রিটার্ন কমানোর গাইড'],
            ['path' => '/ads-roas-calculator', 'label' => 'Ads ROAS'],
            ['path' => '/bd-fraud-checker', 'label' => 'ফ্রড চেকার'],
            ['path' => '/faq', 'label' => 'সব FAQ'],
        ],
        'faqs' => [
            [
                'q' => 'প্রতি রিটার্নে কী কী খরচ ধরব?',
                'a' => 'ডেলিভারি+রিটার্ন চার্জ, প্যাকিং, সময়, এবং নষ্ট অ্যাড CPA। ক্যালকুলেটর: /return-loss-calculator',
            ],
            [
                'q' => 'রিটার্ন কমাতে প্রথম ধাপ?',
                'a' => 'কনফার্মের আগে হিস্টোরি চেক। /faq/customer-delivery-history-check',
            ],
            [
                'q' => 'ফেক Purchase ROAS কীভাবে দেখব?',
                'a' => '/ads-roas-calculator',
            ],
        ],
    ],
];
