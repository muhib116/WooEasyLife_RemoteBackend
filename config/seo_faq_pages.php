<?php

/**
 * FAQ hub + question page meta (BN first).
 * Slugs must match config/seo_keyword_inventory.php planned_faq rows.
 *
 * Copy rules: never use bare `/` as a word separator (linkify → “হোম”).
 * Prefer বনাম · ও , — and put paths only where PATH_LABELS exist.
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
    ['path' => '/faq/phone-confirm-delivery-guarantee-ki', 'label' => 'ফোন কনফার্ম গ্যারান্টি?'],
    ['path' => '/faq/history-na-thakle-ki-korbo', 'label' => 'হিস্টোরি না থাকলে'],
    ['path' => '/faq/prottek-customer-verify-korbo-ki', 'label' => 'প্রত্যেককে ভেরিফাই?'],
    ['path' => '/faq/wooeasylife-fraud-predict-kore-ki', 'label' => 'ফ্রড predict করে কি?'],
    ['path' => '/faq/fake-order-chinhe-fela-jay-ki', 'label' => 'ফেক অর্ডার চিনা যায়?'],
    ['path' => '/faq/steadfast-return-request-kivabe', 'label' => 'SteadFast Return Request'],
    ['path' => '/faq/steadfast-stuck-parcel-ki-korbo', 'label' => 'SteadFast Stuck Parcel'],
];

$toolLinks = [
    ['path' => '/steadfast-fraud-check', 'label' => 'SteadFast Fraud Check গাইড'],
    ['path' => '/bd-fraud-checker', 'label' => 'ফ্রি ফ্রড চেকার'],
    ['path' => '/blog/steadfast-fraud-check-case-study', 'label' => 'SteadFast কেস স্টাডি'],
    ['path' => '/return-loss-calculator', 'label' => 'রিটার্ন লস ক্যালকুলেটর'],
    ['path' => '/fake-order-protection', 'label' => 'ফেক অর্ডার প্রোটেকশন'],
    ['path' => '/courier-auto-entry', 'label' => 'কুরিয়ার অটো এন্ট্রি'],
    ['path' => '/pricing', 'label' => 'প্রাইসিং ও ট্রায়াল'],
];

return [
    'faq' => [
        'title' => 'FAQ — ফ্রড চেক, OTP, ব্ল্যাকলিস্ট ও COD রিটার্ন | WooEasyLife',
        'description' => 'WooEasyLife FAQ: কুরিয়ার সাকসেস রেট, ফ্রড স্কোর, COD OTP, কাস্টমার ব্ল্যাকলিস্ট, ডুপ্লিকেট অর্ডার ব্লক ও রিটার্ন লস হিসাব—BD WooCommerce সেলারদের জন্য।',
        'canonical_path' => '/faq',
        'og_type' => 'website',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'WooEasyLife FAQ — ফ্রড, OTP ও COD প্রশ্নোত্তর',
        'prerender_lead' => 'ফেক অর্ডার, কুরিয়ার হিস্টোরি, চেকআউট OTP, ব্ল্যাকলিস্ট ও রিটার্ন লস নিয়ে সবচেয়ে বেশি জিজ্ঞাসিত প্রশ্ন—সরাসরি উত্তর ও টুল লিংক।',
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
                'a' => 'কুরিয়ার সাকসেস রেট হলো মোট ডেলিভারি চেষ্টার মধ্যে কতটি অর্ডার সফলভাবে পৌঁছেছে এবং কতটি ক্যানসেল, রিটার্ন বা ব্যর্থ হয়েছে তার অনুপাত। রেট ভালো হলেও নতুন ঠিকানা বা অস্বাভাবিক অর্ডার হলে ছোট একটি কল করে নিশ্চিত হওয়া নিরাপদ, আর বারবার রিটার্ন দেখা গেলে পাঠানোর আগে কারণ যাচাই করুন। হিসাব বোঝার ধাপ ও সিদ্ধান্তের উদাহরণ দেখতে /faq/courier-success-rate-kivabe-bujhbo দেখুন।',
            ],
            [
                'q' => 'সাকসেস রেট কম হলে কী করব?',
                'a' => 'সাকসেস রেট কম দেখলেই অর্ডার বাতিল করবেন না; আগে নম্বর, ঠিকানা ও আগের ডেলিভারি রেকর্ড মিলিয়ে দেখুন। কাস্টমারের সঙ্গে কথা বলে অর্ডার নিশ্চিত করুন, প্রয়োজন হলে OTP নিন বা যাচাই শেষ না হওয়া পর্যন্ত অর্ডার হোল্ডে রাখুন। ধাপে ধাপে সিদ্ধান্ত নেওয়ার নিয়ম জানতে /faq/success-rate-kom-hole-ki-korbo এবং বর্তমান রেকর্ড দেখতে /bd-fraud-checker ব্যবহার করুন।',
            ],
            [
                'q' => 'COD অর্ডারে OTP কখন নেব?',
                'a' => 'সব COD অর্ডারে OTP বাধ্যতামূলক করার দরকার নেই, কারণ বিশ্বস্ত পুরোনো কাস্টমারের জন্য অতিরিক্ত ধাপটি অপ্রয়োজনীয় ঝামেলা তৈরি করতে পারে। নতুন নম্বর, কম সাকসেস রেট, বারবার অর্ডার বা সন্দেহজনক তথ্য দেখা গেলে OTP নিয়ে নম্বরের মালিকানা নিশ্চিত করা বেশি কার্যকর। কোন পরিস্থিতিতে OTP চালু করবেন তা জানতে /faq/cod-order-otp-kokhon দেখুন।',
            ],
            [
                'q' => 'WooCommerce-এ কাস্টমার ব্ল্যাকলিস্ট কীভাবে?',
                'a' => 'WooCommerce স্টোরে বারবার ফেক অর্ডার বা অযৌক্তিক রিটার্ন করা কাস্টমারকে ফোন, ইমেইল, IP ও ডিভাইসের তথ্য দিয়ে ব্ল্যাকলিস্ট করা যায়। তবে একটি খারাপ অর্ডারের ভিত্তিতে তাড়াহুড়ো না করে আগের রেকর্ড, যোগাযোগের ফল ও পুনরাবৃত্ত আচরণ মিলিয়ে সিদ্ধান্ত নিন। নিরাপদভাবে রুল বসানোর নির্দেশনা পেতে /faq/woocommerce-customer-blacklist দেখুন।',
            ],
            [
                'q' => 'ডুপ্লিকেট COD অর্ডার কীভাবে আটকাব?',
                'a' => 'একই ফোন নম্বর বা একই কার্ট দিয়ে অল্প সময়ে বারবার COD অর্ডার এলে ডুপ্লিকেট ব্লক এবং দৈনিক অর্ডার লিমিট ব্যবহার করুন। এতে বৈধ ক্রেতাকে অযথা বাধা না দিয়ে স্প্যাম অর্ডার, অপ্রয়োজনীয় প্যাকিং ও কুরিয়ার খরচ কমানো যায়। রুল কীভাবে ঠিকভাবে সেট ও পরীক্ষা করবেন তা জানতে /faq/duplicate-cod-order-block দেখুন।',
            ],
            [
                'q' => 'কাস্টমার ডেলিভারি হিস্টোরি কীভাবে চেক করব?',
                'a' => 'কাস্টমারের মোবাইল নম্বর দিয়ে Pathao, Steadfast ও RedX-এ আগের সফল ডেলিভারি এবং রিটার্নের রেকর্ড একসঙ্গে দেখা যায়। অর্ডার পাঠানোর আগে এই ইতিহাস দেখে ঝুঁকি বুঝুন, তবে একটি ব্যর্থ ডেলিভারিকে একা চূড়ান্ত প্রমাণ হিসেবে ধরবেন না। যাচাইয়ের নিয়ম জানতে /faq/customer-delivery-history-check এবং সরাসরি চেক করতে /bd-fraud-checker দেখুন।',
            ],
            [
                'q' => 'কাস্টমার ফ্রড স্কোর কী?',
                'a' => 'কাস্টমার ফ্রড স্কোর বিভিন্ন আচরণ ও অর্ডার সিগন্যাল মিলিয়ে সম্ভাব্য ঝুঁকি বোঝায়, কিন্তু এটি কাউকে নিশ্চিত প্রতারক ঘোষণা করে না। স্কোর উদ্বেগজনক হলে ডেলিভারি হিস্টোরি দেখুন, কাস্টমারকে কল করুন এবং প্রয়োজন হলে OTP নিয়ে তারপর সিদ্ধান্ত নিন। স্কোরের অর্থ ও সঠিক ব্যবহার জানতে /faq/customer-fraud-score-ki দেখুন।',
            ],
            [
                'q' => 'COD রিটার্ন লস কীভাবে হিসাব করব?',
                'a' => 'মাসিক COD রিটার্ন লস বের করতে দৈনিক অর্ডার, ৩০ দিন, রিটার্ন রেট এবং প্রতি রিটার্নে হওয়া মোট খরচ গুণ করুন। মোট খরচে ডেলিভারি চার্জ, রিটার্ন ফি, প্যাকিং, কর্মঘণ্টা ও নষ্ট বিজ্ঞাপন ব্যয় রাখলে হিসাব বাস্তবসম্মত হবে। বিস্তারিত ফর্মুলা জানতে /faq/cod-return-loss-hisab এবং নিজের সংখ্যা বসাতে /return-loss-calculator ব্যবহার করুন।',
            ],
        ],
    ],

    'faq_courier_success_rate_kivabe_bujhbo' => [
        'title' => 'কুরিয়ার সাকসেস রেট কীভাবে বুঝব — COD গাইড | WooEasyLife',
        'description' => 'কুরিয়ার সাকসেস রেট কীভাবে বুঝবেন: delivered বনাম ক্যানসেল ও রিটার্ন অনুপাত, BD COD-তে কত হলে ভালো, এবং অর্ডার কনফার্মের আগে কী দেখবেন।',
        'canonical_path' => '/faq/courier-success-rate-kivabe-bujhbo',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'কুরিয়ার সাকসেস রেট কীভাবে বুঝব?',
        'prerender_lead' => 'delivered বনাম রিটার্ন ও ক্যানসেল অনুপাত পড়ে COD কনফার্মের আগে ঝুঁকি বোঝার সহজ নিয়ম।',
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
                'a' => '৭০ শতাংশ বা তার বেশি সফল ডেলিভারি সাধারণত তুলনামূলক ভালো সংকেত, তবে একে সবার জন্য নিশ্চয়তা হিসেবে ধরা ঠিক নয়। মোট অর্ডারের সংখ্যা, সাম্প্রতিক রিটার্ন, নতুন ঠিকানা এবং অর্ডারের মূল্যও একসঙ্গে বিবেচনা করুন। রেট কম বা তথ্য সীমিত হলে কীভাবে নিরাপদ সিদ্ধান্ত নেবেন তা /faq/success-rate-kom-hole-ki-korbo পাতায় দেখুন।',
            ],
            [
                'q' => 'শুধু এক কুরিয়ার দেখলেই কি যথেষ্ট?',
                'a' => 'শুধু একটি কুরিয়ারের রেকর্ড দেখলে কাস্টমারের পূর্ণ ডেলিভারি আচরণ বোঝা নাও যেতে পারে। Pathao-তে ভালো রেকর্ড থাকা একজনের Steadfast বা RedX-এ বেশি রিটার্ন থাকতে পারে, তাই তিনটির তথ্য একসঙ্গে দেখে মোট অর্ডারের সংখ্যাও বিবেচনা করুন। সমন্বিত রেকর্ড দেখতে /bd-fraud-checker ব্যবহার করুন।',
            ],
            [
                'q' => 'রেট ও স্কোর কি একই জিনিস?',
                'a' => 'সাকসেস রেট ও ফ্রড স্কোর একই বিষয় নয়, তাই একটি দেখে অন্যটির সিদ্ধান্ত টানা উচিত নয়। সাকসেস রেট সফল ডেলিভারি ও রিটার্নের অনুপাত দেখায়, আর ফ্রড স্কোর বিভিন্ন আচরণগত সিগন্যাল থেকে সম্ভাব্য ঝুঁকি বোঝায়। দুটির পার্থক্য ও ব্যবহার আরও পরিষ্কারভাবে জানতে /faq/customer-fraud-score-ki দেখুন।',
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
        'prerender_lead' => 'হলুদ বা লাল জোনে অন্ধভাবে পার্সেল পাঠানো নয়—কল, OTP বা হোল্ড দিয়ে রিটার্ন ৳ বাঁচান।',
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
                'a' => 'কম সাকসেস রেট মানেই কাস্টমার ফেক, এমন সিদ্ধান্ত নেওয়া ঠিক নয়। ভুল ঠিকানা, জরুরি অনুপস্থিতি বা কুরিয়ার যোগাযোগের সমস্যার কারণেও একটি বা দুটি ডেলিভারি ব্যর্থ হতে পারে। তাই সম্পূর্ণ হিস্টোরি দেখুন, কাস্টমারকে কল করে তথ্য মিলিয়ে নিন এবং প্রয়োজন হলে OTP বা হোল্ড ব্যবহার করুন; রেকর্ড দেখতে /bd-fraud-checker ব্যবহার করতে পারেন।',
            ],
            [
                'q' => 'বারবার কম রেট হলে কী করব?',
                'a' => 'একই নম্বরে বারবার রিটার্ন হলে প্রতিটি ঘটনার কারণ, কলের ফল এবং কাস্টমারের প্রতিক্রিয়া অর্ডার নোটে লিখে রাখুন। পরের অর্ডারে OTP নিন, তথ্য আবার যাচাই করুন এবং পুনরাবৃত্ত ফেক আচরণ নিশ্চিত হলে তবেই ব্ল্যাকলিস্ট করুন। ন্যায্য ব্লকিং রুল ও ব্যবস্থাপনার ধাপ জানতে /faq/woocommerce-customer-blacklist দেখুন।',
            ],
            [
                'q' => 'রিটার্ন লস কত হচ্ছে কীভাবে দেখব?',
                'a' => 'রিটার্নে প্রকৃত ক্ষতি জানতে দৈনিক অর্ডার, মাসের কার্যদিবস, রিটার্ন রেট এবং প্রতিটি ফেরত পার্সেলের মোট খরচ লিখে নিন। কুরিয়ার ফি, প্যাকিং, কর্মঘণ্টা ও নষ্ট বিজ্ঞাপন ব্যয় বাদ দিলে হিসাব অসম্পূর্ণ থাকবে। পূর্ণ ফর্মুলা /faq/cod-return-loss-hisab পাতায় আছে, আর দ্রুত ফল পেতে /return-loss-calculator ব্যবহার করুন।',
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
                'a' => 'সব COD অর্ডারে OTP দেওয়া জরুরি নয়, কারণ পরিচিত ও নিয়মিত কাস্টমারের জন্য এটি চেকআউটে বাড়তি বাধা তৈরি করতে পারে। নতুন নম্বর, কম সাকসেস রেট, অস্বাভাবিক অর্ডার মূল্য বা বারবার অর্ডারের মতো ঝুঁকির সংকেত থাকলে OTP চালু করুন। ঝুঁকিভিত্তিক ভেরিফিকেশন কীভাবে সাজাবেন তা /fake-order-protection পাতায় দেখুন।',
            ],
            [
                'q' => 'OTP ছাড়া আর কী লাগে?',
                'a' => 'OTP শুধু ফোন নম্বরের নিয়ন্ত্রণ যাচাই করে, তাই একে একমাত্র প্রতিরক্ষা হিসেবে ব্যবহার করা যথেষ্ট নয়। একই কার্টের ডুপ্লিকেট ব্লক, দৈনিক অর্ডার লিমিট, কাস্টমার হিস্টোরি এবং প্রমাণভিত্তিক ব্ল্যাকলিস্ট একসঙ্গে রাখলে পুনরাবৃত্ত ফেক অর্ডার ভালোভাবে সামলানো যায়। ডুপ্লিকেট নিয়মের জন্য /faq/duplicate-cod-order-block এবং ব্ল্যাকলিস্টের জন্য /faq/woocommerce-customer-blacklist দেখুন।',
            ],
            [
                'q' => 'মোবাইল অ্যাপে কি approve করা যায়?',
                'a' => 'WooEasyLife মোবাইল অ্যাপে সংযুক্ত স্টোরের নতুন অর্ডারের পুশ নোটিফিকেশন দেখা এবং বাইরে থেকেও অর্ডার অনুমোদন বা হোল্ড করা যায়। কল আইডি ও ইনবাউন্ড কল ম্যাচ থাকায় কাস্টমারের সঙ্গে কথা বলার সময় সংশ্লিষ্ট অর্ডার শনাক্ত করাও সহজ হয়। এই সুবিধাগুলো ব্যবহারের আগে স্টোর সংযোগ ও অনুমতি ঠিক আছে কি না যাচাই করুন; প্রাসঙ্গিক সুরক্ষা অপশন দেখতে /fake-order-protection দেখুন।',
            ],
        ],
    ],

    'faq_woocommerce_customer_blacklist' => [
        'title' => 'WooCommerce কাস্টমার ব্ল্যাকলিস্ট কীভাবে — ফোন ও IP ব্লক | WooEasyLife',
        'description' => 'WooCommerce-এ ফেক কাস্টমার ব্ল্যাকলিস্ট: ফোন, ইমেইল, IP, ডিভাইস ব্লক করে বারবার COD রিটার্ন ও স্প্যাম অর্ডার আটকান।',
        'canonical_path' => '/faq/woocommerce-customer-blacklist',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'WooCommerce কাস্টমার ব্ল্যাকলিস্ট কীভাবে করবেন?',
        'prerender_lead' => 'বারবার রিটার্ন বা ফেক নম্বর একবার ব্লক করলে একই ক্ষতি বারবার হয় না।',
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
                'a' => 'শুধু ফোন নম্বর ব্লক করলে সব পুনরাবৃত্ত ফেক অর্ডার আটকানো নাও যেতে পারে, কারণ কেউ ইমেইল বা ডিভাইস বদলে আবার চেষ্টা করতে পারে। নিশ্চিত আচরণগত প্রমাণ থাকলে ফোনের সঙ্গে ইমেইল, IP ও ডিভাইস সিগন্যাল মিলিয়ে রুল দিন, তবে শেয়ার করা নেটওয়ার্কের বৈধ ক্রেতার কথাও বিবেচনা করুন। উপলভ্য সুরক্ষা স্তরগুলো /fake-order-protection পাতায় দেখুন।',
            ],
            [
                'q' => 'কুরিয়ার কি আমার হয়ে ব্লক করে?',
                'a' => 'কুরিয়ার প্রতিষ্ঠানের ডেলিভারি রেকর্ড আপনার সিদ্ধান্তে সাহায্য করে, কিন্তু আপনার WooCommerce স্টোরের ব্ল্যাকলিস্ট সাধারণত আপনাকেই পরিচালনা করতে হয়। কোন কাস্টমারকে কেন ব্লক করা হলো তার প্রমাণ ও নোট রাখুন, যাতে টিম একই নিয়ম অনুসরণ করে এবং ভুল ব্লক সহজে সংশোধন করা যায়। রিটার্নের পর সিদ্ধান্ত নেওয়ার বাস্তব উদাহরণ পেতে /blog/blacklist-customer-after-returns পড়ুন।',
            ],
            [
                'q' => 'চেকের পর কখন ব্লক করব?',
                'a' => 'একই কাস্টমারের বারবার অযৌক্তিক রিটার্ন, ভুয়া তথ্য বা নিশ্চিত স্প্যাম আচরণের পরিষ্কার প্যাটার্ন পাওয়ার পর ব্লক করা যুক্তিযুক্ত। একটি খারাপ রেকর্ড বা একবার ফোন না ধরার কারণে স্থায়ী ব্লক না দিয়ে আগে যোগাযোগ, ঠিকানা ও কুরিয়ার হিস্টোরি যাচাই করুন। সিদ্ধান্তের আগে সমন্বিত রেকর্ড দেখতে /bd-fraud-checker ব্যবহার করুন।',
            ],
        ],
    ],

    'faq_duplicate_cod_order_block' => [
        'title' => 'ডুপ্লিকেট COD অর্ডার ব্লক — একই কাস্টমার স্প্যাম আটকান | WooEasyLife',
        'description' => 'একই কাস্টমারের একাধিক COD বা স্প্যাম অর্ডার কীভাবে ব্লক করবেন: ডুপ্লিকেট কার্ট ব্লক, দৈনিক লিমিট ও ফেক অর্ডার প্রোটেকশন।',
        'canonical_path' => '/faq/duplicate-cod-order-block',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'ডুপ্লিকেট COD অর্ডার কীভাবে ব্লক করবেন?',
        'prerender_lead' => 'একই কার্ট বা নম্বর দিয়ে বারবার অর্ডার—প্যাকিং ও কুরিয়ার ফি নষ্ট করার আগেই আটকান।',
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
                'a' => 'রুল খুব কঠোর করলে বৈধ পুনরায় অর্ডারও আটকে যেতে পারে, তাই শুধু একই কার্ট ও নির্ধারিত সময়সীমার পুনরাবৃত্তির মতো স্পষ্ট শর্ত ব্যবহার করুন। আলাদা পণ্য নেওয়া বা যুক্তিসঙ্গত বিরতির পর আবার অর্ডার করা ক্রেতার জন্য অনুমোদনের সুযোগ রাখুন। লাইভ করার আগে পরীক্ষামূলক অর্ডার দিয়ে আচরণ যাচাই করুন এবং সেটআপের বিকল্পগুলো /fake-order-protection পাতায় দেখুন।',
            ],
            [
                'q' => 'দৈনিক লিমিট কী?',
                'a' => 'দৈনিক লিমিট নির্ধারণ করে একটি ফোন নম্বর থেকে এক দিনে সর্বোচ্চ কতটি অর্ডার গ্রহণ করা হবে। এটি অল্প সময়ে করা স্প্যাম ও টেস্ট অর্ডার কমায়, তবে নিয়মিত পাইকারি ক্রেতা থাকলে তাদের স্বাভাবিক অর্ডার ভলিউম বিবেচনা করে সীমা ঠিক করতে হবে। লিমিটের পাশাপাশি OTP, ডুপ্লিকেট কার্ট চেক ও প্রমাণভিত্তিক ব্ল্যাকলিস্ট ব্যবহার করলে সুরক্ষা আরও কার্যকর হয়।',
            ],
            [
                'q' => 'চেকআউট ছাড়াও কি লাগে?',
                'a' => 'চেকআউটে ডুপ্লিকেট ব্লক থাকলেও অর্ডার কনফার্মের আগে কাস্টমারের আগের ডেলিভারি হিস্টোরি দেখা দরকার। একই কার্ট না হলেও কম সাকসেস রেট, নতুন ঠিকানা বা পুনরাবৃত্ত রিটার্ন ঝুঁকির ইঙ্গিত দিতে পারে। কী তথ্য দেখবেন এবং সেই তথ্য থেকে কীভাবে সিদ্ধান্ত নেবেন তা /faq/customer-delivery-history-check পাতায় ব্যাখ্যা করা হয়েছে।',
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
                'a' => 'WooEasyLife-এর ফ্রড চেকারে Pathao, Steadfast ও RedX-এর উপলভ্য ডেলিভারি রেকর্ড একসঙ্গে দেখা যায়। প্রতিটি কুরিয়ারের সফল ডেলিভারি, রিটার্ন ও মোট অর্ডারের সংখ্যা মিলিয়ে দেখলে শুধু একটি উৎসের তুলনায় কাস্টমারের আচরণ ভালো বোঝা যায়। মোবাইল নম্বর দিয়ে বর্তমান উপলভ্য রেকর্ড দেখতে /bd-fraud-checker ব্যবহার করুন।',
            ],
            [
                'q' => 'এটি কি ফ্রি?',
                'a' => 'ল্যান্ডিং পেজ থেকে অ্যাকাউন্ট ছাড়াই সীমিত সংখ্যক ফ্রি চেক করা যায়, তাই নতুন সেলারও আগে ফল দেখে নিতে পারেন। বেশি অর্ডার থাকলে প্রতিটি ফলকে কাস্টমার কল, ঠিকানা ও অর্ডার মূল্যের সঙ্গে মিলিয়ে একটি নির্দিষ্ট যাচাই প্রক্রিয়া অনুসরণ করুন। বিনামূল্যে চেক শুরু করতে /bd-fraud-checker দেখুন।',
            ],
            [
                'q' => 'চেকের পর কী করব?',
                'a' => 'উচ্চ সাকসেস রেট ও পর্যাপ্ত পুরোনো অর্ডার থাকলে সাধারণ যাচাই শেষে অর্ডার কনফার্ম করা যায়। রেট কম, মোট অর্ডার খুব কম বা সাম্প্রতিক রিটার্ন বেশি হলে কাস্টমারকে কল করুন, ঠিকানা মিলিয়ে নিন এবং প্রয়োজন হলে OTP বা হোল্ড ব্যবহার করুন। কম রেটের জন্য পূর্ণ সিদ্ধান্তধারা /faq/success-rate-kom-hole-ki-korbo পাতায় দেখুন।',
            ],
        ],
    ],

    'faq_customer_fraud_score_ki' => [
        'title' => 'কাস্টমার ফ্রড স্কোর কী — অর্থ ও ব্যবহার | WooEasyLife',
        'description' => 'কাস্টমার ফ্রড বা বিহেভিয়ার স্কোর কী, কীভাবে হিসাব হয়, এবং COD কনফার্মে কীভাবে ব্যবহার করবেন—ভেরডিক্ট নয়, সিগন্যাল।',
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
                'a' => 'ফ্রড স্কোর কোনো আদালতের রায় বা নিশ্চিত প্রতারণার প্রমাণ নয়; এটি কেবল সম্ভাব্য ঝুঁকি বুঝতে সহায়ক একটি সিগন্যাল। স্কোর উদ্বেগজনক হলে অর্ডার সরাসরি বাতিল না করে ডেলিভারি হিস্টোরি, ঠিকানা, কলের উত্তর ও OTP ফল মিলিয়ে দেখুন। কাস্টমারের তথ্য যাচাই শুরু করতে /fake-customer-check ব্যবহার করুন।',
            ],
            [
                'q' => 'রেট না দেখে শুধু স্কোর?',
                'a' => 'শুধু স্কোর দেখে সিদ্ধান্ত নিলে কাস্টমারের বাস্তব ডেলিভারি ইতিহাসের গুরুত্বপূর্ণ অংশ বাদ পড়তে পারে। সাকসেস রেট অতীতের সফল ডেলিভারি ও রিটার্নের অনুপাত দেখায়, আর স্কোর আচরণগত ও অর্ডারভিত্তিক ঝুঁকির সিগন্যাল দেয়। দুই তথ্য একসঙ্গে কীভাবে পড়বেন তা বুঝতে /faq/courier-success-rate-kivabe-bujhbo দেখুন।',
            ],
            [
                'q' => 'পুনরাবৃত্তি ঝুঁকি হলে?',
                'a' => 'একই কাস্টমারের ক্ষেত্রে কম স্কোর, পুনরাবৃত্ত রিটার্ন ও অসংগত তথ্য বারবার মিললে প্রতিটি ঘটনার নোট সংরক্ষণ করুন। পরের অর্ডারে OTP ও কল যাচাই ব্যবহার করুন, এবং ফেক আচরণ নিশ্চিত হলে তবেই ফোন, ইমেইল বা প্রাসঙ্গিক সিগন্যাল দিয়ে ব্লক করুন। ন্যায্যভাবে ব্ল্যাকলিস্ট পরিচালনার নির্দেশনা /faq/woocommerce-customer-blacklist পাতায় আছে।',
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
        'prerender_lead' => 'মাসিক ৳ লস না জানলে “কমেছে কি?” বোঝা যায় না—সহজ ফর্মুলা ও ক্যালকুলেটর।',
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
                'a' => 'প্রতি রিটার্নের খরচে সামনের ডেলিভারি চার্জ, রিটার্ন ফি, প্যাকিং উপকরণ, পণ্য ক্ষতির ঝুঁকি এবং টিমের ব্যয় করা সময় ধরুন। যে অর্ডারটি ডেলিভারিই হয়নি তার জন্য ব্যবহৃত বিজ্ঞাপন ব্যয়ও যোগ করলে প্রকৃত ব্যবসায়িক ক্ষতি পরিষ্কার হবে। সব সংখ্যা এক জায়গায় বসিয়ে মাসিক প্রভাব দেখতে /return-loss-calculator ব্যবহার করুন।',
            ],
            [
                'q' => 'রিটার্ন কমাতে প্রথম ধাপ?',
                'a' => 'প্রথমে অর্ডার কনফার্মের আগে কাস্টমারের কুরিয়ার ডেলিভারি হিস্টোরি দেখার একটি নিয়মিত ধাপ চালু করুন। রেট কম বা তথ্য সন্দেহজনক হলে কাস্টমারকে কল করুন, ঠিকানা নিশ্চিত করুন এবং প্রয়োজন অনুযায়ী OTP নিন; শুধু অনুমানের ভিত্তিতে পার্সেল পাঠাবেন না। যাচাইয়ের প্রক্রিয়া /faq/customer-delivery-history-check পাতায় এবং চেকিং টুল /bd-fraud-checker পাতায় পাবেন।',
            ],
            [
                'q' => 'ফেক Purchase ROAS কীভাবে দেখব?',
                'a' => 'Meta Pixel-এ Purchase ইভেন্ট দেখা গেলেও সব COD অর্ডার শেষ পর্যন্ত ডেলিভারি না হলে রিপোর্টেড ROAS বাস্তব আয়ের চেয়ে বেশি দেখাতে পারে। তাই বিজ্ঞাপন থেকে আসা অর্ডারের মধ্যে কতটি ডেলিভারি হয়েছে, কতটি রিটার্ন হয়েছে এবং প্রকৃত রাজস্ব কত তা আলাদা করে হিসাব করুন। ডেলিভারি অনুযায়ী সমন্বয় করা ফল দেখতে /ads-roas-calculator ব্যবহার করুন।',
            ],
        ],
    ],
] + require __DIR__.'/seo_faq_spokes/step3_new_faq_pages.php';
