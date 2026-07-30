<?php

/**
 * Step 3 — page meta for new SteadFast cluster FAQ URLs.
 */

$steadfastClusterLinks = [
    ['path' => '/steadfast-fraud-check', 'label' => 'SteadFast Fraud Check গাইড'],
    ['path' => '/bd-fraud-checker', 'label' => 'ফ্রি ফ্রড চেকার'],
    ['path' => '/faq', 'label' => 'সব FAQ'],
    ['path' => '/blog/steadfast-fraud-check-faq', 'label' => 'SteadFast FAQ ইনডেক্স'],
    ['path' => '/pricing', 'label' => 'প্রাইসিং'],
];

return [
    'faq_phone_confirm_delivery_guarantee_ki' => [
        'title' => 'ফোন কনফার্ম কি ডেলিভারি গ্যারান্টি দেয়? | WooEasyLife FAQ',
        'description' => 'ফোন কনফার্ম ডেলিভারি গ্যারান্টি দেয় না। SteadFast COD-এ কনফার্ম + হিস্টোরি + OTP কেন একসাথে লাগে।',
        'canonical_path' => '/faq/phone-confirm-delivery-guarantee-ki',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'ফোন কনফার্ম কি ডেলিভারি গ্যারান্টি দেয়?',
        'prerender_lead' => 'না। “নিবো” শোনা যথেষ্ট নয়—হিস্টোরি ও ঝুঁকি জোনে OTP যোগ করুন।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/steadfast-fraud-check',
        'hreflang_paths' => [
            'bn-BD' => '/faq/phone-confirm-delivery-guarantee-ki',
            'x-default' => '/faq/phone-confirm-delivery-guarantee-ki',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'ফোন কনফার্ম গ্যারান্টি', 'path' => '/faq/phone-confirm-delivery-guarantee-ki'],
        ],
        'cluster_links' => array_merge([
            ['path' => '/faq/customer-delivery-history-check', 'label' => 'হিস্টোরি চেক'],
            ['path' => '/faq/cod-order-otp-kokhon', 'label' => 'OTP কখন'],
            ['path' => '/faq/prottek-customer-verify-korbo-ki', 'label' => 'কখন ভেরিফাই'],
        ], $steadfastClusterLinks),
        'faqs' => [
            [
                'q' => 'শুধু কল করে শিপ করলে কী সমস্যা?',
                'a' => 'কল শুধু মুহূর্তের সম্মতি ধরে; আগের রিটার্ন প্যাটার্ন ধরে না। রাইডার পৌঁছালে ফোন বন্ধ বা রিফিউজ হতে পারে। হিস্টোরি মিলিয়ে নিন: /faq/customer-delivery-history-check।',
            ],
            [
                'q' => 'কখন OTP যোগ করব?',
                'a' => 'কম রেট, নতুন নম্বর বা হাই-টিকেটে OTP কার্যকর। বিস্তারিত: /faq/cod-order-otp-kokhon।',
            ],
            [
                'q' => 'SteadFast-এ কোথায় শুরু করব?',
                'a' => 'আগে /steadfast-fraud-check বা /bd-fraud-checker চালান, তারপর কনফার্ম ও বুকিং।',
            ],
        ],
    ],

    'faq_history_na_thakle_ki_korbo' => [
        'title' => 'হিস্টোরি না থাকলে কী করব — নতুন নম্বর COD | WooEasyLife',
        'description' => 'কুরিয়ার হিস্টোরি খালি হলে অটো-রিজেক্ট নয়। নতুন নম্বরে কনফার্ম, OTP ও ঝুঁকি নীতি কীভাবে প্রয়োগ করবেন।',
        'canonical_path' => '/faq/history-na-thakle-ki-korbo',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'হিস্টোরি না থাকলে কী করব?',
        'prerender_lead' => 'খালি হিস্টোরি = অটো-রিজেক্ট নয়, অন্ধ শিপও নয়—স্ট্যান্ডার্ড যাচাই ও ঝুঁকি নীতি।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/steadfast-fraud-check',
        'hreflang_paths' => [
            'bn-BD' => '/faq/history-na-thakle-ki-korbo',
            'x-default' => '/faq/history-na-thakle-ki-korbo',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'হিস্টোরি নেই', 'path' => '/faq/history-na-thakle-ki-korbo'],
        ],
        'cluster_links' => array_merge([
            ['path' => '/faq/customer-delivery-history-check', 'label' => 'হিস্টোরি কী'],
            ['path' => '/faq/prottek-customer-verify-korbo-ki', 'label' => 'কখন ভেরিফাই'],
            ['path' => '/faq/cod-order-otp-kokhon', 'label' => 'OTP কখন'],
        ], $steadfastClusterLinks),
        'faqs' => [
            [
                'q' => 'নতুন নম্বর কি ফেক?',
                'a' => 'না। নতুন সিম বা প্রথমবার ক্রেতায় রেকর্ড খালি থাকতে পারে। স্ট্যান্ডার্ড কনফার্ম করুন; হাই-টিকেটে OTP নিন।',
            ],
            [
                'q' => 'কীভাবে নিশ্চিত করব রেকর্ড সত্যিই খালি?',
                'a' => '/steadfast-fraud-check বা /bd-fraud-checker চালিয়ে দেখুন। এক কুরিয়ার প্যানেল যথেষ্ট নয়।',
            ],
            [
                'q' => 'পরে রিটার্ন হলে?',
                'a' => 'নোটে লিখুন এবং পরের অর্ডারে কঠোর নীতি দিন। পুনরাবৃত্তিতে /faq/woocommerce-customer-blacklist।',
            ],
        ],
    ],

    'faq_prottek_customer_verify_korbo_ki' => [
        'title' => 'প্রত্যেক কাস্টমার ভেরিফাই করব কি? | WooEasyLife FAQ',
        'description' => 'সব অর্ডারে একই কঠোরতা নয়। রিস্ক-ভিত্তিক ভেরিফিকেশন—নতুন নম্বর, হাই টিকেট, কম রেটে কঠোর যাচাই।',
        'canonical_path' => '/faq/prottek-customer-verify-korbo-ki',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'প্রত্যেক কাস্টমার ভেরিফাই করব কি?',
        'prerender_lead' => 'রিস্ক-ভিত্তিক নীতি ভালো—ঝুঁকি বেশি যেখানে, যাচাই সেখানে কঠোর।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/steadfast-fraud-check',
        'hreflang_paths' => [
            'bn-BD' => '/faq/prottek-customer-verify-korbo-ki',
            'x-default' => '/faq/prottek-customer-verify-korbo-ki',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'কখন ভেরিফাই', 'path' => '/faq/prottek-customer-verify-korbo-ki'],
        ],
        'cluster_links' => array_merge([
            ['path' => '/blog/kokhon-customer-verify-korbo', 'label' => 'কখন ভেরিফাই ব্লগ'],
            ['path' => '/customer-verification', 'label' => 'কাস্টমার ভেরিফিকেশন'],
            ['path' => '/faq/success-rate-kom-hole-ki-korbo', 'label' => 'রেট কম হলে'],
        ], $steadfastClusterLinks),
        'faqs' => [
            [
                'q' => 'সব অর্ডারে OTP কি লাগবে?',
                'a' => 'না। ঝুঁকি জোনে OTP যথেষ্ট। বিস্তারিত: /faq/cod-order-otp-kokhon।',
            ],
            [
                'q' => 'সবুজ জোনে কী করব?',
                'a' => 'দ্রুত কনফার্ম ও /courier-auto-entry। তবু নীতি অনুযায়ী একবার নম্বর চেক রাখুন।',
            ],
            [
                'q' => 'টিম নীতি কোথায় লিখব?',
                'a' => 'জোন রুল: /customer-verification। ব্লগ চেকলিস্ট: /blog/kokhon-customer-verify-korbo।',
            ],
        ],
    ],

    'faq_wooeasylife_fraud_predict_kore_ki' => [
        'title' => 'WooEasyLife কি ফ্রড predict করে? | সততা FAQ',
        'description' => 'WooEasyLife ফ্রড গ্যারান্টি বা predict করে না—হিস্টোরি ও সুরক্ষা দিয়ে informed decision সাহায্য করে।',
        'canonical_path' => '/faq/wooeasylife-fraud-predict-kore-ki',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'WooEasyLife কি ফ্রড predict করে?',
        'prerender_lead' => 'না। টুল সিগন্যাল দেয়—ফেক বা জেনুইন বলে গ্যারান্টি দেয় না।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/steadfast-fraud-check',
        'hreflang_paths' => [
            'bn-BD' => '/faq/wooeasylife-fraud-predict-kore-ki',
            'x-default' => '/faq/wooeasylife-fraud-predict-kore-ki',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'ফ্রড predict?', 'path' => '/faq/wooeasylife-fraud-predict-kore-ki'],
        ],
        'cluster_links' => array_merge([
            ['path' => '/fake-customer-check', 'label' => 'Fake Customer Check'],
            ['path' => '/faq/customer-fraud-score-ki', 'label' => 'ফ্রড স্কোর কী'],
            ['path' => '/faq/fake-order-chinhe-fela-jay-ki', 'label' => 'ফেক অর্ডার চিনা'],
        ], $steadfastClusterLinks),
        'faqs' => [
            [
                'q' => 'স্কোর কি চূড়ান্ত রায়?',
                'a' => 'না—ঝুঁকি সিগন্যাল। বিস্তারিত: /faq/customer-fraud-score-ki।',
            ],
            [
                'q' => 'তাহলে টুলের কাজ কী?',
                'a' => 'হিস্টোরি ও রেশিও দেখিয়ে কনফার্ম, OTP বা হোল্ড সিদ্ধান্ত ভালো করা। পিলার: /steadfast-fraud-check।',
            ],
            [
                'q' => 'সততা লাইন কী?',
                'a' => 'This tool helps you make a better-informed decision. It does not guarantee that an order is fake or genuine.',
            ],
        ],
    ],

    'faq_fake_order_chinhe_fela_jay_ki' => [
        'title' => 'ফেক অর্ডার কি চিনে ফেলা যায়? | WooEasyLife FAQ',
        'description' => 'ফেক অর্ডার ১০০% ধরা যায় না। হিস্টোরি, রেট, OTP ও প্রোটেকশন দিয়ে ঝুঁকি কমানোর বাস্তব উপায়।',
        'canonical_path' => '/faq/fake-order-chinhe-fela-jay-ki',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/fraud-layers.jpg',
        'prerender_h1' => 'ফেক অর্ডার কি চিনে ফেলা যায়?',
        'prerender_lead' => 'আংশিক—সিগন্যাল দিয়ে ঝুঁকি কমানো যায়; ১০০% মেশিন দাবি করা ভুল।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/steadfast-fraud-check',
        'hreflang_paths' => [
            'bn-BD' => '/faq/fake-order-chinhe-fela-jay-ki',
            'x-default' => '/faq/fake-order-chinhe-fela-jay-ki',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'ফেক অর্ডার চিনা', 'path' => '/faq/fake-order-chinhe-fela-jay-ki'],
        ],
        'cluster_links' => array_merge([
            ['path' => '/ki-vabe-fake-order-atkabo', 'label' => 'ফেক অর্ডার গাইড'],
            ['path' => '/fake-order-protection', 'label' => 'প্রোটেকশন'],
            ['path' => '/faq/wooeasylife-fraud-predict-kore-ki', 'label' => 'Predict করে কি?'],
        ], $steadfastClusterLinks),
        'faqs' => [
            [
                'q' => 'ফেক অর্ডার কি ১০০% চিনা যায়?',
                'a' => 'না। This tool helps you make a better-informed decision. It does not guarantee that an order is fake or genuine. সিগন্যাল দিয়ে ঝুঁকি কমানো যায়—চূড়ান্ত verdict নয়।',
            ],
            [
                'q' => 'কোন সিগন্যাল সবচেয়ে শক্তিশালী?',
                'a' => 'সাম্প্রতিক একাধিক রিটার্ন ও মাল্টি-কুরিয়ার খারাপ প্যাটার্ন। দেখুন: /faq/customer-delivery-history-check।',
            ],
            [
                'q' => 'এক রিটার্ন দেখে ব্লক?',
                'a' => 'না—প্যাটার্ন দেখুন। ব্ল্যাকলিস্ট FAQ: /faq/woocommerce-customer-blacklist।',
            ],
            [
                'q' => 'লেয়ার কী কী?',
                'a' => 'চেক → কল বা OTP → ডুপ্লিকেট ব্লক → ব্ল্যাকলিস্ট → Return Hub। গাইড: /ki-vabe-fake-order-atkabo।',
            ],
        ],
    ],

    'faq_steadfast_return_request_kivabe' => [
        'title' => 'SteadFast Return Request কীভাবে Confirm বা Resend করব? | FAQ',
        'description' => 'SteadFast cancel বা return request WooEasyLife Return Hub থেকে Decide করুন—Confirm cancel বা Ask to resend।',
        'canonical_path' => '/faq/steadfast-return-request-kivabe',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/steadfast-return-decide-modal.jpg',
        'prerender_h1' => 'SteadFast Return Request কীভাবে করব?',
        'prerender_lead' => 'পোর্টালে ঘোরা কমিয়ে Return Requests থেকে Confirm cancel বা Ask to resend Decide করুন।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/steadfast-return-hub',
        'hreflang_paths' => [
            'bn-BD' => '/faq/steadfast-return-request-kivabe',
            'x-default' => '/faq/steadfast-return-request-kivabe',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'SteadFast Return Request', 'path' => '/faq/steadfast-return-request-kivabe'],
        ],
        'cluster_links' => [
            ['path' => '/steadfast-return-hub', 'label' => 'SteadFast Return Hub'],
            ['path' => '/steadfast-integration', 'label' => 'SteadFast ইন্টিগ্রেশন'],
            ['path' => '/faq/steadfast-stuck-parcel-ki-korbo', 'label' => 'Stuck পার্সেল'],
            ['path' => '/steadfast-fraud-check', 'label' => 'Fraud Check গাইড'],
            ['path' => '/faq', 'label' => 'সব FAQ'],
        ],
        'faqs' => [
            [
                'q' => 'পোর্টালে আর যেতে হবে?',
                'a' => 'দৈনন্দিন Decide-এর জন্য সাধারণত না। বিস্তারিত: /steadfast-return-hub।',
            ],
            [
                'q' => 'Pathao-তেও কি আছে?',
                'a' => 'না—এই হাব SteadFast-কেন্দ্রিক।',
            ],
            [
                'q' => 'আগে রিটার্ন কমাতে কী করব?',
                'a' => 'প্রি-শিপ চেক: /steadfast-fraud-check · /blog/steadfast-return-komano।',
            ],
        ],
    ],

    'faq_steadfast_stuck_parcel_ki_korbo' => [
        'title' => 'SteadFast Stuck Parcel হলে কী করব? | FAQ',
        'description' => 'SteadFast পার্সেল আটকে গেলে Notifications-এ Scan stuck চালান—কল, Decide বা ফলো-আপ।',
        'canonical_path' => '/faq/steadfast-stuck-parcel-ki-korbo',
        'og_type' => 'article',
        'og_image' => '/images/seo/cluster/steadfast-return-requests-queue.jpg',
        'prerender_h1' => 'SteadFast Stuck Parcel হলে কী করব?',
        'prerender_lead' => 'Scan stuck দিয়ে নিস্তব্ধ পার্সেল আগে ধরুন—stuck মানেই সবসময় cancel নয়।',
        'page_kind' => 'faq_question',
        'pillar_path' => '/steadfast-return-hub',
        'hreflang_paths' => [
            'bn-BD' => '/faq/steadfast-stuck-parcel-ki-korbo',
            'x-default' => '/faq/steadfast-stuck-parcel-ki-korbo',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'FAQ', 'path' => '/faq'],
            ['name' => 'Stuck পার্সেল', 'path' => '/faq/steadfast-stuck-parcel-ki-korbo'],
        ],
        'cluster_links' => [
            ['path' => '/steadfast-return-hub', 'label' => 'SteadFast Return Hub'],
            ['path' => '/faq/steadfast-return-request-kivabe', 'label' => 'Return Request'],
            ['path' => '/steadfast-integration', 'label' => 'ইন্টিগ্রেশন'],
            ['path' => '/return-loss-calculator', 'label' => 'রিটার্ন লস'],
            ['path' => '/faq', 'label' => 'সব FAQ'],
        ],
        'faqs' => [
            [
                'q' => 'Stuck মানেই কি ক্যানসেল?',
                'a' => 'না—রাইডার ব্যস্ত বা নেটওয়ার্ক সমস্যাও হতে পারে। আগে কল করুন।',
            ],
            [
                'q' => 'কতদিন পর স্ক্যান?',
                'a' => 'সাধারণত ~৩ দিন quiet ডিফল্ট; সাপ্তাহিক রুটিন রাখুন। হাব: /steadfast-return-hub।',
            ],
            [
                'q' => 'Return request থাকলে?',
                'a' => '/faq/steadfast-return-request-kivabe অনুসরণ করে Decide করুন।',
            ],
        ],
    ],
];
