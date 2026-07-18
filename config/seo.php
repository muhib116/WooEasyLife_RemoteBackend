<?php

return [
    'site_name' => 'WooEasyLife',

    // Prefer JPG for Facebook / LinkedIn scrapers (webp is less consistent).
    'default_og_image' => '/images/seo/og-default.jpg',

    'og_image_width' => 1200,
    'og_image_height' => 630,

    /*
    | Optional. Meta Developer App → Settings → Basic → App ID.
    | Clears Facebook Sharing Debugger "missing fb:app_id" warning.
    | Falls back to FACEBOOK_CLIENT_ID if you already use Facebook Login.
    */
    'facebook_app_id' => env('SEO_FB_APP_ID', env('FACEBOOK_CLIENT_ID')),

    'html_lang' => 'bn-BD',

    'gsc' => [
        // Property URL as verified in Search Console, e.g. https://wooeasylife.com/
        'site_url' => env('SEO_GSC_SITE_URL'),
        /*
        | OAuth (preferred): reuse Google Cloud OAuth client + a refresh token
        | with scope https://www.googleapis.com/auth/webmasters.readonly
        */
        'client_id' => env('SEO_GSC_CLIENT_ID', env('GOOGLE_CLIENT_ID')),
        'client_secret' => env('SEO_GSC_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET')),
        'refresh_token' => env('SEO_GSC_REFRESH_TOKEN', env('GOOGLE_GSC_REFRESH_TOKEN')),
        // Legacy fallback: short-lived access token (avoid in production).
        'access_token' => env('SEO_GSC_ACCESS_TOKEN'),
        // One-click admin connect callback (must match Google Cloud OAuth redirect URI).
        'oauth_redirect' => env('SEO_GSC_REDIRECT_URI'),
    ],

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
            'title' => 'Free Courier Fraud Checker BD — ফ্রড চেকার | WooEasyLife',
            'description' => 'ফ্রি Courier Fraud Checker BD ও ফ্রড চেকার। মোবাইল নম্বর দিয়ে Pathao, Steadfast, RedX ডেলিভারি হিস্টোরি, সাকসেস রেট ও রিটার্ন রেট দেখুন। ফেক অর্ডার আটকান — ই-কমার্স ও Facebook পেজ সেলারদের জন্য।',
            'canonical_path' => '/',
            'prerender_h1' => 'Free Courier Fraud Checker BD — ফ্রি ফ্রড চেকার',
            'prerender_lead' => 'মোবাইল নম্বর দিয়ে কাস্টমারের কুরিয়ার হিস্টোরি ও ডেলিভারি রেশিও চেক করুন। ফেক অর্ডার আটকান, রিটার্ন লস কমান — WooCommerce ও Facebook পেজ ব্যবসার জন্য।',
            'hreflang_paths' => [
                'bn-BD' => '/',
                'en' => '/en',
                'x-default' => '/',
            ],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
            ],
            'faqs' => [
                [
                    'q' => 'ফ্রড চেকার কী?',
                    'a' => 'ফ্রড চেকার বা Courier Fraud Checker BD হলো টুল যা কাস্টমারের ফোন নম্বর দিয়ে কুরিয়ার ডেলিভারি হিস্টোরি, সাকসেস রেট ও রিটার্ন রেট দেখায় — অর্ডার কনফার্মের আগে ফেক কাস্টমার চেক করতে।',
                ],
                [
                    'q' => 'কিভাবে ফেক অর্ডার আটকাবো?',
                    'a' => 'প্রথমে মোবাইল নম্বর দিয়ে ফ্রড/কুরিয়ার হিস্টোরি চেক করুন, তারপর চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চালু করুন। বিস্তারিত গাইড: /ki-vabe-fake-order-atkabo',
                ],
                [
                    'q' => 'ফোন নম্বর দিয়ে কুরিয়ার হিস্টোরি কীভাবে দেখি?',
                    'a' => 'WooEasyLife হোমপেজ বা /bd-fraud-checker পেজে ফোন নম্বর দিলে Pathao, Steadfast, RedX সহ সাপোর্টেড কুরিয়ারের ডেলিভারি রেকর্ড ও রেশিও দেখা যায় — অ্যাকাউন্ট ছাড়াই দৈনিক ফ্রি চেক।',
                ],
                [
                    'q' => 'Fake customer check কীভাবে করব?',
                    'a' => '/fake-customer-check পেজে নম্বর দিয়ে ডেলিভারি সাকসেস রেট ও রিটার্ন হিস্টোরি দেখে সিদ্ধান্ত নিন।',
                ],
                [
                    'q' => 'শুধু ফ্রড টুলের সাথে WooEasyLife-এর পার্থক্য কী?',
                    'a' => 'অনেক টুল শুধু হিস্টোরি দেখায়। WooEasyLife-এ ফ্রড চেক ছাড়াও চেকআউট OTP, ফেক অর্ডার ব্লক, কুরিয়ার অটো এন্ট্রি, হারানো অর্ডার রিকভারি ও মোবাইল অ্যাপ একসাথে পাবেন।',
                ],
                [
                    'q' => 'কুরিয়ার অটো এন্ট্রি কী?',
                    'a' => 'অর্ডার কনফার্ম হলেই Pathao, Steadfast বা RedX-এ পার্সেল এন্ট্রি স্বয়ংক্রিয় হয় — কুরিয়ার প্যানেলে বারবার ম্যানুয়ালি টাইপ করতে হয় না।',
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
            'title' => 'Free Courier Fraud Checker BD — ফ্রি ফ্রড চেকার | WooEasyLife',
            'description' => 'Free Courier Fraud Checker BD। মোবাইল নম্বর দিয়ে Pathao, Steadfast, RedX ডেলিভারি হিস্টোরি ও সাকসেস রেট চেক করুন — ফেক অর্ডার ও পার্সেল রিটার্ন কমান। অ্যাকাউন্ট ছাড়াই ফ্রি সার্চ।',
            'canonical_path' => '/bd-fraud-checker',
            'prerender_h1' => 'Free Courier Fraud Checker BD — ফ্রি ফ্রড চেকার',
            'prerender_lead' => 'কাস্টমারের মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি, ডেলিভারি সাকসেস রেট ও রিটার্ন রেকর্ড তাৎক্ষণিক দেখুন। অর্ডার কনফার্মের আগেই ফেক কাস্টমার চেক করুন।',
            'hreflang_paths' => [
                'bn-BD' => '/bd-fraud-checker',
                'en' => '/en/bd-fraud-checker',
                'x-default' => '/bd-fraud-checker',
            ],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Courier Fraud Checker BD', 'path' => '/bd-fraud-checker'],
            ],
            'faqs' => [
                [
                    'q' => 'Courier Fraud Checker BD কীভাবে কাজ করে?',
                    'a' => 'কাস্টমারের মোবাইল নম্বর দিলে Pathao, Steadfast, RedX সহ সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন হিস্টোরি একসাথে দেখায়। সাকসেস রেট কম হলে অর্ডার কনফার্মের আগে সিদ্ধান্ত নিন।',
                ],
                [
                    'q' => 'Fake order check কি ফ্রি?',
                    'a' => 'হ্যাঁ — ল্যান্ডিং পেজে অ্যাকাউন্ট ছাড়াই প্রতিদিন সীমিত সংখ্যক ফ্রি চেক করা যায়।',
                ],
                [
                    'q' => 'কোন কোন কুরিয়ার চেক করা যায়?',
                    'a' => 'Pathao, Steadfast, RedX সহ WooEasyLife-এ সাপোর্টেড প্রধান কুরিয়ারগুলোর রেকর্ড দেখা যায়।',
                ],
                [
                    'q' => 'শুধু চেক করলেই কি যথেষ্ট?',
                    'a' => 'চেক শুরু। পূর্ণ সুরক্ষায় চেকআউট OTP, ফেক অর্ডার ব্লক ও কুরিয়ার অটো এন্ট্রি লাগে — WooEasyLife এক প্ল্যাটফর্মে সব দেয়।',
                ],
            ],
        ],

        'fake_order_protection' => [
            'title' => 'কিভাবে ফেক অর্ডার আটকাবো — Fake Order Check & Protection | WooEasyLife',
            'description' => 'কিভাবে ফেক অর্ডার আটকাবো? মোবাইল নম্বর দিয়ে fake customer check, কুরিয়ার হিস্টোরি, OTP ও ব্ল্যাকলিস্ট দিয়ে পার্সেল রিটার্ন কমান। WooCommerce ও Facebook পেজ সেলারদের জন্য।',
            'canonical_path' => '/fake-order-protection',
            'prerender_h1' => 'কিভাবে ফেক অর্ডার আটকাবো — Fake Order Protection',
            'prerender_lead' => 'ফ্রড চেক + OTP + ব্লক — মাল্টি-লেয়ার প্রোটেকশনে ফেক অর্ডার ও রিটার্ন লস কমান।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'ফেক অর্ডার প্রোটেকশন', 'path' => '/fake-order-protection'],
            ],
            'faqs' => [
                [
                    'q' => 'ফেক অর্ডার কেন হয়?',
                    'a' => 'COD ও Facebook পেজ ব্যবসায় অনেকে ভুল নম্বর বা মজার অর্ডার দেয়। পার্সেল পাঠালে রিটার্ন চার্জ বিক্রেতার ঘাড়ে পড়ে।',
                ],
                [
                    'q' => 'কিভাবে ফেক অর্ডার আটকাবো?',
                    'a' => 'মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি চেক করুন, চেকআউট OTP চালু করুন, ডুপ্লিকেট ও ব্ল্যাকলিস্ট রাখুন। ধাপে ধাপে গাইড: /ki-vabe-fake-order-atkabo',
                ],
                [
                    'q' => 'WooEasyLife কীভাবে ফেক অর্ডার আটকায়?',
                    'a' => 'কুরিয়ার হিস্টোরি, চেকআউট OTP, ডুপ্লিকেট অর্ডার ব্লক, ফোন/ইমেইল/ডিভাইস ব্লক ও দৈনিক অর্ডার সীমা — একসাথে মাল্টি-লেয়ার প্রোটেকশন।',
                ],
            ],
        ],

        'ki_vabe_fake_order_atkabo' => [
            'title' => 'কিভাবে ফেক অর্ডার আটকাবো? — সম্পূর্ণ গাইড | WooEasyLife',
            'description' => 'কিভাবে ফেক অর্ডার আটকাবো (ki vabe fake order atkabo)? মোবাইল নম্বর ফ্রড চেক, সাকসেস রেট, OTP ও ব্ল্যাকলিস্ট দিয়ে পার্সেল রিটার্ন ও অ্যাড লস কমান — বাংলাদেশ COD সেলার গাইড।',
            'canonical_path' => '/ki-vabe-fake-order-atkabo',
            'prerender_h1' => 'কিভাবে ফেক অর্ডার আটকাবো?',
            'prerender_lead' => 'ধাপে ধাপে: ফ্রড চেক → যাচাই → ব্লক → কুরিয়ার অটো এন্ট্রি। টুল-শুধু চেকার নয় — পূর্ণ WooCommerce সুরক্ষা।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'কিভাবে ফেক অর্ডার আটকাবো', 'path' => '/ki-vabe-fake-order-atkabo'],
            ],
            'faqs' => [
                [
                    'q' => 'কিভাবে ফেক অর্ডার আটকাবো সবচেয়ে দ্রুত?',
                    'a' => 'অর্ডার কনফার্মের আগে মোবাইল নম্বর দিয়ে Courier Fraud Checker BD চালান। সাকসেস রেট খারাপ হলে পাঠাবেন না।',
                ],
                [
                    'q' => 'শুধু ফ্রি টুল কি যথেষ্ট?',
                    'a' => 'ফ্রি চেক শুরু। বারবার ফেক অর্ডার আটকাতে OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট লাগে — WooEasyLife-এ সব একসাথে।',
                ],
            ],
        ],

        'fake_customer_check' => [
            'title' => 'Fake Customer Check — মোবাইল নম্বর দিয়ে ফ্রড কাস্টমার চেক | WooEasyLife',
            'description' => 'Fake customer check BD। মোবাইল নম্বর দিয়ে কাস্টমারের কুরিয়ার ডেলিভারি হিস্টোরি, রিটার্ন রেট ও গুণমান চেক করুন — ফেক অর্ডার আটকান, ফ্রি সার্চ।',
            'canonical_path' => '/fake-customer-check',
            'prerender_h1' => 'Fake Customer Check — মোবাইল নম্বর দিয়ে ফ্রড কাস্টমার চেক',
            'prerender_lead' => 'অর্ডার কনফার্মের আগেই কাস্টমার যাচাই করুন। ফোন নম্বর দিয়ে সার্চ করুন — কুরিয়ার হিস্টোরি ও রিটার্ন রেট তাৎক্ষণিক।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Fake Customer Check', 'path' => '/fake-customer-check'],
            ],
            'faqs' => [
                [
                    'q' => 'Fake customer check কী?',
                    'a' => 'কাস্টমারের ফোন নম্বর দিয়ে আগের ডেলিভারি সাকসেস/রিটার্ন হিস্টোরি দেখে তিনি বিশ্বস্ত কিনা বোঝা।',
                ],
                [
                    'q' => 'এটি কি ফ্রি?',
                    'a' => 'হ্যাঁ — দৈনিক সীমিত ফ্রি চেক অ্যাকাউন্ট ছাড়াই করা যায়।',
                ],
            ],
        ],

        'bd_courier_ratio_checker' => [
            'title' => 'BD Courier Order Ratio Checker — সাকসেস রেট চেক | WooEasyLife',
            'description' => 'BD Courier order Ratio Checker। ফোন নম্বর দিয়ে ডেলিভারি সাকসেস রেট ও রিটার্ন রেশিও চেক করুন — Pathao, Steadfast, RedX। পার্সেল রিটার্ন কমান, ফ্রি অনলাইন চেক।',
            'canonical_path' => '/bd-courier-ratio-checker',
            'prerender_h1' => 'BD Courier Order Ratio Checker — ডেলিভারি সাকসেস রেট',
            'prerender_lead' => 'কাস্টমার কোয়ালিটি অনলাইন ও ফ্রি চেক করুন। সাকসেস রেট কম হলে অর্ডার আগে যাচাই করুন।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'BD Courier Ratio Checker', 'path' => '/bd-courier-ratio-checker'],
            ],
            'faqs' => [
                [
                    'q' => 'Order ratio checker কী দেখায়?',
                    'a' => 'ডেলিভারি সাকসেস বনাম রিটার্ন/ক্যানসেল হিস্টোরি — কাস্টমার কোয়ালিটি বোঝার জন্য।',
                ],
                [
                    'q' => 'Courier number check online কিভাবে?',
                    'a' => 'এই পেজে বা /bd-fraud-checker-এ বাংলাদেশি মোবাইল নম্বর দিয়ে সার্চ করুন।',
                ],
            ],
        ],

        'fake_order_check' => [
            'title' => 'Fake Order Check BD — ফ্রি কুরিয়ার ফ্রড চেকার | WooEasyLife',
            'description' => 'Fake order check ও Courier Checker BD। ফোন নম্বর দিয়ে ফেক অর্ডার ঝুঁকি দেখুন — ডেলিভারি হিস্টোরি, রিটার্ন রেট, ফ্রি অনলাইন চেক।',
            'canonical_path' => '/fake-order-check',
            'prerender_h1' => 'Fake Order Check BD — ফ্রি কুরিয়ার ফ্রড চেকার',
            'prerender_lead' => 'ফেক অর্ডার চেক করুন মোবাইল নম্বর দিয়ে। কুরিয়ার হিস্টোরি দেখে পার্সেল পাঠানোর আগে সিদ্ধান্ত নিন।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Fake Order Check', 'path' => '/fake-order-check'],
            ],
            'faqs' => [
                [
                    'q' => 'Fake order check আর fraud checker একই?',
                    'a' => 'প্রায় একই ইন্টেন্ট — নম্বর দিয়ে কুরিয়ার হিস্টোরি দেখে ফেক/ঝুঁকিপূর্ণ অর্ডার চিনা।',
                ],
            ],
        ],

        'courier_checker' => [
            'title' => 'Courier Checker BD — কুরিয়ার নম্বর চেক অনলাইন ফ্রি | WooEasyLife',
            'description' => 'Courier Checker ও BD Courier check। কাস্টমার মোবাইল নম্বর দিয়ে অনলাইন ফ্রি কুরিয়ার হিস্টোরি ও কোয়ালিটি চেক — Pathao, Steadfast, RedX।',
            'canonical_path' => '/courier-checker',
            'prerender_h1' => 'Courier Checker BD — অনলাইন ফ্রি কুরিয়ার চেক',
            'prerender_lead' => 'Courier number check online — নম্বর দিন, হিস্টোরি দেখুন, ফেক অর্ডার এড়ান।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Courier Checker', 'path' => '/courier-checker'],
            ],
            'faqs' => [
                [
                    'q' => 'BD Courier check কী?',
                    'a' => 'বাংলাদেশি কুরিয়ার নেটওয়ার্কে কাস্টমারের আগের পার্সেল ডেলিভারি/রিটার্ন রেকর্ড যাচাই।',
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

        'pathao_fraud_check' => [
            'title' => 'Pathao Fraud Check বাংলাদেশ — ফ্রি কুরিয়ার হিস্টোরি | WooEasyLife',
            'description' => 'Pathao fraud check বাংলাদেশ। ফোন নম্বর দিয়ে Pathao কুরিয়ার ডেলিভারি ও রিটার্ন হিস্টোরি চেক করুন। COD ফেক অর্ডার কমান — ফ্রি BD fraud checker।',
            'canonical_path' => '/pathao-fraud-check',
            'prerender_h1' => 'Pathao Fraud Check বাংলাদেশ',
            'prerender_lead' => 'ফোন নম্বর দিয়ে Pathao কুরিয়ার হিস্টোরি যাচাই করুন। অর্ডার কনফার্মের আগে ফেক অর্ডারের ঝুঁকি কমান।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Pathao Fraud Check', 'path' => '/pathao-fraud-check'],
            ],
            'faqs' => [
                [
                    'q' => 'Pathao fraud check কীভাবে কাজ করে?',
                    'a' => 'কাস্টমারের মোবাইল নম্বর দিলে WooEasyLife Pathao সহ সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন হিস্টোরি দেখায়।',
                ],
                [
                    'q' => 'শুধু Pathao দেখা যায় নাকি অন্য কুরিয়ারও?',
                    'a' => 'একই টুলে Pathao, Steadfast, RedX সহ সাপোর্টেড কুরিয়ারের রেকর্ড একসাথে দেখা যায়।',
                ],
                [
                    'q' => 'এটি কি ফ্রি?',
                    'a' => 'হ্যাঁ — অ্যাকাউন্ট ছাড়াই দৈনিক সীমিত ফ্রি চেক করা যায়।',
                ],
            ],
        ],

        'steadfast_fraud_check' => [
            'title' => 'Steadfast Fraud Check বাংলাদেশ — ফ্রি কুরিয়ার হিস্টোরি | WooEasyLife',
            'description' => 'Steadfast fraud check বাংলাদেশ। ফোন নম্বর দিয়ে Steadfast কুরিয়ার ডেলিভারি ও রিটার্ন হিস্টোরি চেক করুন। COD ফেক অর্ডার কমান।',
            'canonical_path' => '/steadfast-fraud-check',
            'prerender_h1' => 'Steadfast Fraud Check বাংলাদেশ',
            'prerender_lead' => 'ফোন নম্বর দিয়ে Steadfast কুরিয়ার হিস্টোরি যাচাই করুন। অর্ডার কনফার্মের আগে ফেক অর্ডারের ঝুঁকি কমান।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Steadfast Fraud Check', 'path' => '/steadfast-fraud-check'],
            ],
            'faqs' => [
                [
                    'q' => 'Steadfast fraud check কীভাবে কাজ করে?',
                    'a' => 'মোবাইল নম্বর ইনপুট করলে Steadfast সহ সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন রেকর্ড দেখা যায়।',
                ],
                [
                    'q' => 'Pathao ও Steadfast দুটোই কি চেক করা যায়?',
                    'a' => 'হ্যাঁ — একই BD fraud checker টুলে একাধিক কুরিয়ারের হিস্টোরি একসাথে পাবেন।',
                ],
                [
                    'q' => 'ফ্রি কিভাবে চেক করব?',
                    'a' => 'এই পেজে বা /bd-fraud-checker-এ নম্বর দিয়ে অ্যাকাউন্ট ছাড়াই চেক করুন।',
                ],
            ],
        ],

        'redx_fraud_check' => [
            'title' => 'RedX Fraud Check বাংলাদেশ — ফ্রি কুরিয়ার হিস্টোরি | WooEasyLife',
            'description' => 'RedX fraud check বাংলাদেশ। ফোন নম্বর দিয়ে RedX কুরিয়ার ডেলিভারি ও রিটার্ন হিস্টোরি চেক করুন। COD ফেক অর্ডার কমান।',
            'canonical_path' => '/redx-fraud-check',
            'prerender_h1' => 'RedX Fraud Check বাংলাদেশ',
            'prerender_lead' => 'ফোন নম্বর দিয়ে RedX কুরিয়ার হিস্টোরি যাচাই করুন। অর্ডার কনফার্মের আগে ফেক অর্ডারের ঝুঁকি কমান।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'RedX Fraud Check', 'path' => '/redx-fraud-check'],
            ],
            'faqs' => [
                [
                    'q' => 'RedX fraud check কীভাবে কাজ করে?',
                    'a' => 'কাস্টমারের ফোন নম্বর দিলে RedX সহ সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন হিস্টোরি দেখা যায়।',
                ],
                [
                    'q' => 'অন্যান্য কুরিয়ারও কি একসাথে দেখা যায়?',
                    'a' => 'হ্যাঁ — Pathao, Steadfast ও RedX একই টুলে চেক করা যায়।',
                ],
                [
                    'q' => 'এটি কি ফ্রি?',
                    'a' => 'হ্যাঁ — ল্যান্ডিং পেজে অ্যাকাউন্ট ছাড়াই দৈনিক সীমিত ফ্রি চেক করা যায়।',
                ],
            ],
        ],

        'blog_index' => [
            'title' => 'ব্লগ — ফেক অর্ডার, ফ্রড চেক ও COD টিপস | WooEasyLife',
            'description' => 'WooEasyLife ব্লগ — বাংলাদেশি WooCommerce সেলারদের জন্য ফেক অর্ডার কমানো, কুরিয়ার হিস্টোরি চেক ও রিটার্ন লস কমানোর গাইড।',
            'canonical_path' => '/blog',
            'prerender_h1' => 'ব্লগ — ফেক অর্ডার, ফ্রড চেক ও COD টিপস',
            'prerender_lead' => 'বাংলাদেশি COD সেলারদের জন্য প্র্যাকটিক্যাল গাইড ও টিপস।',
            'hreflang_paths' => [
                'bn-BD' => '/blog',
                'en' => '/en/blog',
                'x-default' => '/blog',
            ],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'ব্লগ', 'path' => '/blog'],
            ],
            'faqs' => [],
        ],

        'en_home' => [
            'title' => 'Free Courier Fraud Checker BD — WooEasyLife for WooCommerce',
            'description' => 'Free Courier Fraud Checker BD. Verify Pathao, Steadfast, RedX history by phone, block fake COD orders, and auto-enter parcels. For Bangladesh e-commerce & Facebook sellers.',
            'canonical_path' => '/en',
            'html_lang' => 'en',
            'prerender_h1' => 'Free Courier Fraud Checker BD for WooCommerce sellers',
            'prerender_lead' => 'Stop fake COD orders, check courier success rates, and automate Pathao, Steadfast, and RedX entry — built for Bangladesh.',
            'hreflang_paths' => [
                'bn-BD' => '/',
                'en' => '/en',
                'x-default' => '/',
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/en'],
            ],
            'faqs' => [
                [
                    'q' => 'What is a BD fraud checker?',
                    'a' => 'It checks a customer phone number against courier delivery and return history so you can decide before confirming a COD order.',
                ],
                [
                    'q' => 'Is WooEasyLife only a checker tool?',
                    'a' => 'No. Besides fraud checks, you get checkout OTP, fake-order blocking, courier auto-entry, missing-order recovery, and a mobile app.',
                ],
            ],
        ],

        'en_bd_fraud_checker' => [
            'title' => 'Free Courier Fraud Checker BD — Phone History Check | WooEasyLife',
            'description' => 'Free Courier Fraud Checker BD. Check Pathao, Steadfast, RedX delivery history and success rate by mobile number. Stop fake COD orders before you ship.',
            'canonical_path' => '/en/bd-fraud-checker',
            'html_lang' => 'en',
            'prerender_h1' => 'Free Courier Fraud Checker BD — delivery history by phone',
            'prerender_lead' => 'Verify customer courier history and success rate before confirming COD orders. Built for Bangladesh WooCommerce & Facebook sellers.',
            'hreflang_paths' => [
                'bn-BD' => '/bd-fraud-checker',
                'en' => '/en/bd-fraud-checker',
                'x-default' => '/bd-fraud-checker',
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/en'],
                ['name' => 'Courier Fraud Checker BD', 'path' => '/en/bd-fraud-checker'],
            ],
            'faqs' => [
                [
                    'q' => 'How does the free check work?',
                    'a' => 'Enter the customer mobile number to see supported courier delivery and return records. Limited free daily checks are available without an account.',
                ],
                [
                    'q' => 'Which couriers are supported?',
                    'a' => 'Pathao, Steadfast, RedX and other couriers supported inside WooEasyLife.',
                ],
            ],
        ],

        'en_blog_index' => [
            'title' => 'Blog — Fake Orders, Fraud Checks & COD Tips | WooEasyLife',
            'description' => 'Guides for Bangladesh WooCommerce sellers on fake order prevention, courier history checks, and reducing return losses.',
            'canonical_path' => '/en/blog',
            'html_lang' => 'en',
            'prerender_h1' => 'Blog — COD Fraud & Operations Guides',
            'prerender_lead' => 'Practical English guides for Bangladesh WooCommerce COD sellers.',
            'hreflang_paths' => [
                'bn-BD' => '/blog',
                'en' => '/en/blog',
                'x-default' => '/blog',
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/en'],
                ['name' => 'Blog', 'path' => '/en/blog'],
            ],
            'faqs' => [],
        ],
    ],

    'sitemap' => [
        'paths' => [
            ['path' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['path' => '/pricing', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/bd-fraud-checker', 'priority' => '0.95', 'changefreq' => 'weekly'],
            ['path' => '/ki-vabe-fake-order-atkabo', 'priority' => '0.95', 'changefreq' => 'weekly'],
            ['path' => '/fake-customer-check', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/bd-courier-ratio-checker', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/fake-order-check', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/courier-checker', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/fake-order-protection', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/courier-auto-entry', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/fraudbd-alternative', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/pathao-fraud-check', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/steadfast-fraud-check', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/redx-fraud-check', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/blog', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['path' => '/en', 'priority' => '0.85', 'changefreq' => 'weekly'],
            ['path' => '/en/bd-fraud-checker', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/en/blog', 'priority' => '0.75', 'changefreq' => 'weekly'],
            ['path' => '/wooeasylife/app/privacy-policy', 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['path' => '/wooeasylife/app/terms-of-service', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ],
    ],
];
