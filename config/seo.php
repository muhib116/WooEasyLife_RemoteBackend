<?php

return [
    'site_name' => 'WooEasyLife',

    'default_og_image' => '/images/seo/og-default.png',

    'og_image_width' => 1536,
    'og_image_height' => 1024,

    'html_lang' => 'bn-BD',

    'organization' => [
        'name' => 'WooEasyLife',
        'description' => 'Bangladesh WooCommerce platform with BD fraud checker, fake order protection, and courier auto-entry.',
        'same_as' => array_values(array_filter([
            env('SEO_SAME_AS_FACEBOOK'),
            env('SEO_SAME_AS_YOUTUBE'),
            env('SEO_SAME_AS_LINKEDIN'),
            env('WOOEASYLIFE_PLAY_STORE_URL'),
        ])),
    ],

    'pages' => [
        'home' => [
            'title' => 'WooEasyLife — BD Fraud Checker ও ফ্রড চেকার | ফেক অর্ডার আটকান',
            'description' => 'ফ্রি BD fraud checker ও ফ্রড চেকার — ফোন নম্বর দিয়ে Pathao, Steadfast, RedX কুরিয়ার হিস্টোরি দেখুন। WooCommerce-এ ফেক অর্ডার ব্লক, কুরিয়ার অটো এন্ট্রি ও হারানো অর্ডার ফেরান। ১৪ দিন ফ্রি ট্রায়াল।',
            'canonical_path' => '/',
            'prerender_h1' => 'WooEasyLife — BD Fraud Checker ও ফ্রড চেকার',
            'prerender_lead' => 'বাংলাদেশি WooCommerce সেলারদের জন্য ফ্রি ফ্রড চেকার, ফেক অর্ডার প্রোটেকশন, কুরিয়ার অটো এন্ট্রি ও হারানো অর্ডার রিকভারি — এক প্ল্যাটফর্মে।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
            ],
            'faqs' => [
                [
                    'q' => 'ফ্রড চেকার কী?',
                    'a' => 'ফ্রড চেকার বা BD fraud checker হলো এমন টুল যা কাস্টমারের ফোন নম্বর দিয়ে কুরিয়ার ডেলিভারি ও রিটার্ন হিস্টোরি দেখায় — অর্ডার কনফার্মের আগে ঝুঁকি বোঝার জন্য।',
                ],
                [
                    'q' => 'ফোন নম্বর দিয়ে কুরিয়ার হিস্টোরি কীভাবে দেখি?',
                    'a' => 'WooEasyLife হোমপেজ বা /bd-fraud-checker পেজে ফোন নম্বর দিলে Pathao, Steadfast, RedX সহ সাপোর্টেড কুরিয়ারের ডেলিভারি রেকর্ড দেখা যায় — অ্যাকাউন্ট ছাড়াই দৈনিক ফ্রি চেক।',
                ],
                [
                    'q' => 'শুধু ফ্রড টুলের সাথে WooEasyLife-এর পার্থক্য কী?',
                    'a' => 'অনেক টুল শুধু হিস্টোরি দেখায়। WooEasyLife-এ ফ্রড চেক ছাড়াও চেকআউট OTP, ফেক অর্ডার ব্লক, কুরিয়ার অটো এন্ট্রি, হারানো অর্ডার রিকভারি ও মোবাইল অ্যাপ একসাথে পাবেন।',
                ],
                [
                    'q' => 'কুরিয়ার অটো এন্ট্রি কী?',
                    'a' => 'অর্ডার কনফার্ম হলেই Pathao, Steadfast বা RedX-এ পার্সেল এন্ট্রি স্বয়ংক্রিয় হয় — কুরিয়ার প্যানেলে বারবার ম্যানুয়ালি টাইপ করতে হয় না।',
                ],
                [
                    'q' => 'ফেক অর্ডার কীভাবে আটকায়?',
                    'a' => 'কুরিয়ার হিস্টোরি চেক, চেকআউট OTP, ডুপ্লিকেট অর্ডার ব্লক, ফোন/IP/ডিভাইস ব্ল্যাকলিস্ট — মাল্টি-লেয়ার প্রোটেকশনে রিটার্ন লস কমানো যায়।',
                ],
            ],
        ],

        'pricing' => [
            'title' => 'প্রাইসিং — WooEasyLife সাবস্ক্রিপশন | ফ্রি ট্রায়াল',
            'description' => 'WooEasyLife প্ল্যান ও মূল্য দেখুন। ফ্রড চেকার, ফেক অর্ডার প্রোটেকশন, কুরিয়ার অটো এন্ট্রি ও মোবাইল অ্যাপ — ১৪ দিন ফ্রি ট্রায়াল, bKash/Nagad/Rocket পেমেন্ট।',
            'canonical_path' => '/pricing',
            'prerender_h1' => 'WooEasyLife প্রাইসিং — সাবস্ক্রিপশন ও ফ্রি ট্রায়াল',
            'prerender_lead' => 'ফ্রড চেকার, ফেক অর্ডার প্রোটেকশন ও কুরিয়ার অটো এন্ট্রিসহ প্ল্যান বেছে নিন। লগইন ছাড়াই সাবস্ক্রিপশন অনুরোধ করা যায়।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'প্রাইসিং', 'path' => '/pricing'],
            ],
            'faqs' => [],
        ],

        'bd_fraud_checker' => [
            'title' => 'BD Fraud Checker — ফ্রি ফ্রড চেকার বাংলাদেশ | WooEasyLife',
            'description' => 'ফ্রি BD fraud checker ও ফ্রড চেকার। ফোন নম্বর দিয়ে Pathao, Steadfast, RedX কুরিয়ার হিস্টোরি চেক করুন। COD ফেক অর্ডার কমান — WooCommerce সেলারদের জন্য।',
            'canonical_path' => '/bd-fraud-checker',
            'prerender_h1' => 'BD Fraud Checker — ফ্রি ফ্রড চেকার বাংলাদেশ',
            'prerender_lead' => 'ফোন নম্বর দিয়ে Pathao, Steadfast ও RedX কুরিয়ার হিস্টোরি যাচাই করুন। অর্ডার কনফার্মের আগে ফেক অর্ডারের ঝুঁকি কমান।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'BD Fraud Checker', 'path' => '/bd-fraud-checker'],
            ],
            'faqs' => [
                [
                    'q' => 'BD fraud checker কীভাবে কাজ করে?',
                    'a' => 'কাস্টমারের মোবাইল নম্বর ইনপুট করলে সিস্টেম সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন হিস্টোরি একসাথে দেখায়। সফল ডেলিভারি হার কম হলে অর্ডার কনফার্মের আগে সিদ্ধান্ত নিন।',
                ],
                [
                    'q' => 'কোন কোন কুরিয়ার চেক করা যায়?',
                    'a' => 'Pathao, Steadfast, RedX সহ WooEasyLife-এ সাপোর্টেড প্রধান কুরিয়ারগুলোর রেকর্ড দেখা যায়।',
                ],
                [
                    'q' => 'এটি কি ফ্রি?',
                    'a' => 'হ্যাঁ — ল্যান্ডিং পেজে অ্যাকাউন্ট ছাড়াই প্রতিদিন সীমিত সংখ্যক ফ্রি চেক করা যায়। পূর্ণ সুরক্ষা ও অটোমেশনের জন্য সাবস্ক্রিপশন নিন।',
                ],
                [
                    'q' => 'শুধু চেক করলেই কি যথেষ্ট?',
                    'a' => 'চেক শুরু। পূর্ণ সুরক্ষায় চেকআউট OTP, ফেক অর্ডার ব্লক ও কুরিয়ার অটো এন্ট্রি লাগে — WooEasyLife এক প্ল্যাটফর্মে সব দেয়।',
                ],
            ],
        ],

        'fake_order_protection' => [
            'title' => 'ফেক অর্ডার বন্ধ — WooCommerce Fake Order Protection | WooEasyLife',
            'description' => 'WooCommerce-এ ফেক অর্ডার বন্ধ করুন। OTP যাচাই, ডুপ্লিকেট ব্লক, ব্ল্যাকলিস্ট ও BD fraud checker দিয়ে রিটার্ন লস কমান। বাংলাদেশ COD সেলারদের জন্য।',
            'canonical_path' => '/fake-order-protection',
            'prerender_h1' => 'ফেক অর্ডার বন্ধ — WooCommerce Fake Order Protection',
            'prerender_lead' => 'OTP, ডুপ্লিকেট ব্লক, ব্ল্যাকলিস্ট ও ফ্রড চেকার দিয়ে COD ফেক অর্ডার আটকান এবং রিটার্ন লস কমান।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'ফেক অর্ডার প্রোটেকশন', 'path' => '/fake-order-protection'],
            ],
            'faqs' => [
                [
                    'q' => 'ফেক অর্ডার কেন হয়?',
                    'a' => 'COD ব্যবসায় অনেকে ভুল নম্বর বা মজার অর্ডার দেয়। পার্সেল পাঠালে রিটার্ন চার্জ বিক্রেতার ঘাড়ে পড়ে।',
                ],
                [
                    'q' => 'WooEasyLife কীভাবে ফেক অর্ডার আটকায়?',
                    'a' => 'কুরিয়ার হিস্টোরি, চেকআউট OTP, ডুপ্লিকেট অর্ডার ব্লক, ফোন/ইমেইল/ডিভাইস ব্লক ও দৈনিক অর্ডার সীমা — একসাথে মাল্টি-লেয়ার প্রোটেকশন।',
                ],
                [
                    'q' => 'ফ্রড চেকার কি আলাদা লাগবে?',
                    'a' => 'না — WooEasyLife-এ বিল্ট-ইন BD fraud checker আছে। আলাদা টুল-শুধু সার্ভিসের ওপর নির্ভর করতে হয় না।',
                ],
            ],
        ],

        'courier_auto_entry' => [
            'title' => 'কুরিয়ার অটো এন্ট্রি — Pathao Steadfast RedX WooCommerce | WooEasyLife',
            'description' => 'অর্ডার কনফার্ম হলেই Pathao, Steadfast, RedX-এ কুরিয়ার অটো এন্ট্রি। WooCommerce COD সেলারদের সময় বাঁচান, স্ট্যাটাস সিঙ্ক ও SMS এক জায়গায়।',
            'canonical_path' => '/courier-auto-entry',
            'prerender_h1' => 'কুরিয়ার অটো এন্ট্রি — Pathao, Steadfast, RedX',
            'prerender_lead' => 'অর্ডার কনফার্ম হলেই কুরিয়ার প্যানেলে অটো এন্ট্রি। ম্যানুয়াল টাইপ বাদ দিয়ে সময় বাঁচান।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'কুরিয়ার অটো এন্ট্রি', 'path' => '/courier-auto-entry'],
            ],
            'faqs' => [
                [
                    'q' => 'কুরিয়ার অটো এন্ট্রি কী?',
                    'a' => 'অর্ডার কনফার্ম করার সাথে সাথে পার্সেল তথ্য কুরিয়ার প্যানেলে স্বয়ংক্রিয়ভাবে এন্ট্রি হয় — ম্যানুয়াল কপি-পেস্ট লাগে না।',
                ],
                [
                    'q' => 'কোন কুরিয়ার সাপোর্ট করে?',
                    'a' => 'Pathao, Steadfast, RedX সহ ইন্টিগ্রেটেড কুরিয়ার — এক ড্যাশবোর্ড থেকে এন্ট্রি ও স্ট্যাটাস আপডেট।',
                ],
                [
                    'q' => 'ফ্রড চেক করার পর কি অটো এন্ট্রি হয়?',
                    'a' => 'আপনি কনফার্ম করলেই এন্ট্রি হয়। আগে ফ্রড চেক করে ঝুঁকি দেখে নিন, তারপর কনফার্ম — নিরাপদ ও দ্রুত ওয়ার্কফ্লো।',
                ],
            ],
        ],

        'fraudbd_alternative' => [
            'title' => 'FraudBD Alternative — WooEasyLife BD Fraud Checker + WooCommerce',
            'description' => 'FraudBD বা শুধু-টুল ফ্রড চেকারের বিকল্প খুঁজছেন? WooEasyLife-এ ফ্রি BD fraud checker ছাড়াও ফেক অর্ডার প্রোটেকশন, কুরিয়ার অটো এন্ট্রি ও মোবাইল অ্যাপ একসাথে।',
            'canonical_path' => '/fraudbd-alternative',
            'prerender_h1' => 'FraudBD Alternative — টুলের বদলে পূর্ণ WooCommerce প্ল্যাটফর্ম',
            'prerender_lead' => 'শুধু ফ্রড হিস্টোরি নয় — WooEasyLife-এ চেক, ব্লক, কুরিয়ার অটো এন্ট্রি ও অর্ডার রিকভারি এক প্ল্যাটফর্মে।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'FraudBD Alternative', 'path' => '/fraudbd-alternative'],
            ],
            'faqs' => [
                [
                    'q' => 'FraudBD আর WooEasyLife-এর মূল পার্থক্য কী?',
                    'a' => 'FraudBD মূলত কুরিয়ার ফ্রড চেক টুল। WooEasyLife-এ ফ্রড চেক আছে, সাথে WooCommerce ফেক অর্ডার প্রোটেকশন, কুরিয়ার অটো এন্ট্রি, হারানো অর্ডার রিকভারি ও মোবাইল অ্যাপ।',
                ],
                [
                    'q' => 'আমি কি ফ্রি চেক করতে পারব?',
                    'a' => 'হ্যাঁ। /bd-fraud-checker পেজে অ্যাকাউন্ট ছাড়াই দৈনিক ফ্রি চেক করা যায়।',
                ],
                [
                    'q' => 'কখন WooEasyLife বেছে নেবেন?',
                    'a' => 'যখন শুধু নম্বর চেক নয়, পুরো COD অপারেশন (প্রোটেকশন + কুরিয়ার + অ্যাপ) এক জায়গায় চান।',
                ],
            ],
        ],
    ],

    'sitemap' => [
        'paths' => [
            ['path' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['path' => '/pricing', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/bd-fraud-checker', 'priority' => '0.95', 'changefreq' => 'weekly'],
            ['path' => '/fake-order-protection', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/courier-auto-entry', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/fraudbd-alternative', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/wooeasylife/app/privacy-policy', 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['path' => '/wooeasylife/app/terms-of-service', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ],
    ],
];
