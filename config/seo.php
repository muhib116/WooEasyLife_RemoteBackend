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
        /*
        | Optional Search Analytics country filter (ISO 3166-1 alpha-3), e.g. bgd.
        | Empty = no country filter (preserves existing live behavior).
        */
        'country' => env('SEO_GSC_COUNTRY'),
    ],

    'ga' => [
        // GA4 property ID (numeric), e.g. 123456789 — Admin → Property settings.
        'property_id' => env('SEO_GA_PROPERTY_ID'),
        /*
        | OAuth (preferred): reuse Google Cloud OAuth client + a refresh token
        | with scope https://www.googleapis.com/auth/analytics.readonly
        */
        'client_id' => env('SEO_GA_CLIENT_ID', env('GOOGLE_CLIENT_ID')),
        'client_secret' => env('SEO_GA_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET')),
        'refresh_token' => env('SEO_GA_REFRESH_TOKEN', env('GOOGLE_GA_REFRESH_TOKEN')),
        // Legacy fallback: short-lived access token (avoid in production).
        'access_token' => env('SEO_GA_ACCESS_TOKEN'),
        // One-click admin connect callback (must match Google Cloud OAuth redirect URI).
        'oauth_redirect' => env('SEO_GA_REDIRECT_URI'),
    ],

    'organization' => [
        'name' => 'WPSaleHub',
        'description' => 'WPSaleHub is a business automation company. Its flagship product WooEasyLife helps WooCommerce merchants in Bangladesh reduce fake orders and run COD operations with fraud checks, protection, and courier automation. Founded by Muhibbullah Ansary.',
        'founder_name' => 'Muhibbullah Ansary',
        'founder_job_title' => 'Founder & CEO',
        'founder_email' => 'dev.muhibbullah@gmail.com',
        'founder_image' => '/images/seo/about/founder-headshot.jpg',
        'founder_url_path' => '/about',
        'founder_same_as' => [
            'https://www.linkedin.com/in/dev-muhib',
            'https://www.facebook.com/muhib116',
            'https://www.instagram.com/muhibbullah611/',
            'https://freetoolssite.com/',
        ],
        'same_as' => array_values(array_filter([
            env('SEO_SAME_AS_FACEBOOK', 'https://www.facebook.com/wooeasylife'),
            env('SEO_SAME_AS_YOUTUBE'),
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
                    'q' => 'মাসিক রিটার্ন লস কীভাবে হিসাব করব?',
                    'a' => '/return-loss-calculator পেজে দৈনিক অর্ডার ও রিটার্ন রেট দিয়ে মাসিক লস ও সম্ভাব্য সাশ্রয় নিজেই হিসাব করুন।',
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
                    'a' => '/fake-customer-check দিয়ে নম্বর চেক করে ডেলিভারি সাকসেস রেট ও রিটার্ন হিস্টোরি দেখে সিদ্ধান্ত নিন।',
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
            'faqs' => [
                [
                    'q' => 'ফ্রি ট্রায়াল কতদিন?',
                    'a' => 'সাধারণত ১৪ দিন ফ্রি ট্রায়াল পাওয়া যায় — প্ল্যান কার্ডে সঠিক দিনসংখ্যা দেখুন। ট্রায়ালে মূল ফিচার টেস্ট করে তারপর আপগ্রেড করতে পারেন।',
                ],
                [
                    'q' => 'কীভাবে পেমেন্ট করব?',
                    'a' => 'bKash, Nagad বা Rocket দিয়ে পেমেন্ট অনুরোধ জমা দিতে পারেন। অ্যাডমিন যাচাইয়ের পর প্ল্যান সক্রিয় হয়।',
                ],
                [
                    'q' => 'লগইন ছাড়া কি সাবস্ক্রিপশন নেওয়া যায়?',
                    'a' => 'হ্যাঁ — প্রাইসিং পেজ থেকে প্ল্যান বেছে সাবস্ক্রিপশন অনুরোধ করা যায়। পরে মার্চেন্ট পোর্টালে লগইন করে স্টোর ও কুরিয়ার সেটআপ করুন।',
                ],
                [
                    'q' => 'প্ল্যানে কী কী থাকে?',
                    'a' => 'প্ল্যান অনুযায়ী ফ্রড চেকার, ফেক অর্ডার প্রোটেকশন, কুরিয়ার অটো এন্ট্রি, এসএমএস, পার্সেল নোট হিস্ট্রি ও মোবাইল অ্যাপ কানেক্ট থাকতে পারে। প্রতিটি কার্ডে ফিচার তালিকা দেখুন।',
                ],
            ],
        ],

        'bd_fraud_checker' => [
            'title' => 'Free Courier Fraud Checker BD — ফোন নম্বরে হিস্টোরি চেক | WooEasyLife',
            'description' => 'Free Courier Fraud Checker BD। মোবাইল নম্বর দিয়ে Pathao, Steadfast, RedX ডেলিভারি হিস্টোরি ও সাকসেস রেট চেক করুন — ফেক অর্ডার ও পার্সেল রিটার্ন কমান। অ্যাকাউন্ট ছাড়াই ফ্রি সার্চ।',
            'canonical_path' => '/bd-fraud-checker',
            'prerender_h1' => 'Free Courier Fraud Checker BD — ফ্রি ফ্রড চেকার',
            'prerender_lead' => 'কাস্টমারের মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি, ডেলিভারি সাকসেস রেট ও রিটার্ন রেকর্ড তাৎক্ষণিক দেখুন। অর্ডার কনফার্মের আগেই ফেক কাস্টমার চেক করুন — Pathao, Steadfast, RedX।',
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
                    'a' => 'কাস্টমারের মোবাইল নম্বর দিলে Pathao, Steadfast, RedX সহ সাপোর্টেড কুরিয়ারের আগের ডেলিভারি, রিটার্ন ও ক্যানসেল হিস্টোরি একসাথে দেখায়। এটি কোনো ব্যক্তিকে নিশ্চিতভাবে “ফ্রড” ঘোষণা করে না; অর্ডার কনফার্মের আগে সিদ্ধান্ত নেওয়ার জন্য আচরণগত সিগন্যাল দেয়। ধাপে ধাপে দেখুন: /faq/customer-delivery-history-check। সব FAQ: /faq।',
                ],
                [
                    'q' => 'Fake order check কি ফ্রি?',
                    'a' => 'হ্যাঁ—এই ল্যান্ডিং পেজে অ্যাকাউন্ট ছাড়াই প্রতিদিন সীমিত সংখ্যক নম্বর চেক করা যায়। ফ্রি চেক ম্যানুয়াল যাচাইয়ের জন্য; বেশি ভলিউম, checkout OTP, duplicate block, blacklist ও automation দরকার হলে /pricing থেকে উপযুক্ত প্ল্যান বা ট্রায়াল দেখুন।',
                ],
                [
                    'q' => 'কোন কোন কুরিয়ার চেক করা যায়?',
                    'a' => 'Pathao, Steadfast, RedX সহ WooEasyLife-এ সাপোর্টেড প্রধান কুরিয়ারগুলোর ডেলিভারি ও রিটার্ন রেকর্ড এক নম্বরে দেখা যায়। প্রতিটি কুরিয়ারের ডেটা আলাদা সিগন্যাল; তাই শুধু একটি রেকর্ড নয়, সম্মিলিত সাকসেস রেট ও সাম্প্রতিক প্যাটার্ন দেখুন। ব্যাখ্যা: /faq/courier-success-rate-kivabe-bujhbo।',
                ],
                [
                    'q' => 'শুধু চেক করলেই কি যথেষ্ট?',
                    'a' => 'চেক হলো প্রথম গেট—এটি ঝুঁকির সিগন্যাল দেয়, কিন্তু একই ব্যক্তি বা ডিভাইসের ভবিষ্যৎ অর্ডার নিজে থেকে বন্ধ করে না। পূর্ণ সুরক্ষায় checkout OTP, duplicate order block ও customer blacklist যোগ করুন। কখন OTP নেবেন: /faq/cod-order-otp-kokhon। ডুপ্লিকেট ব্লক: /faq/duplicate-cod-order-block। ব্ল্যাকলিস্ট: /faq/woocommerce-customer-blacklist। ফিচার ও প্ল্যান: /pricing।',
                ],
                [
                    'q' => 'সাকসেস রেট কম হলে কী করব?',
                    'a' => 'কম সাকসেস রেট দেখলেই সঙ্গে সঙ্গে “ফেক” বলবেন না। আগে ফোন-কনফার্ম ও ঠিকানা যাচাই করুন; ব্যাখ্যা অস্পষ্ট হলে OTP বা অগ্রিম ডেলিভারি চার্জ নিন, নাহলে অর্ডার হোল্ড করুন। বারবার রিটার্ন ও যাচাই ব্যর্থ হলে নোটসহ ব্ল্যাকলিস্ট বিবেচনা করুন। পূর্ণ SOP: /faq/success-rate-kom-hole-ki-korbo।',
                ],
                [
                    'q' => 'কখন COD অর্ডার কনফার্ম না করাই ভালো?',
                    'a' => 'নম্বরের সাকসেস রেট খুব কম, একাধিক সাম্প্রতিক রিটার্ন, ভুল বা অসম্পূর্ণ ঠিকানা, কল না ধরা, OTP ব্যর্থ হওয়া এবং অগ্রিম চার্জে অস্বীকৃতি—একাধিক ঝুঁকি একসাথে থাকলে অন্ধভাবে শিপ করবেন না। হোল্ড করে পুনরায় যাচাই করুন। শুধু একটি পুরোনো রিটার্নকে চূড়ান্ত verdict বানাবেন না।',
                ],
                [
                    'q' => 'এটি কি FraudBD Alternative?',
                    'a' => 'WooEasyLife-এ ফ্রি কুরিয়ার হিস্টোরি চেক দিয়ে শুরু করা যায়; সাবস্ক্রিপশনে WooCommerce OTP, duplicate block, blacklist, courier auto-entry ও অন্যান্য অপারেশন টুল যোগ হয়। তাই তুলনা শুধু checker-to-checker নয়, পরবর্তী workflow-ও দেখুন। বিস্তারিত তুলনা: /fraudbd-alternative।',
                ],
                [
                    'q' => 'রিটার্ন লস কমাতে আর কী দেখব?',
                    'a' => '/return-loss-calculator-এ দৈনিক অর্ডার, রিটার্ন রেট ও প্রতি রিটার্নের খরচ দিয়ে মাসিক COD ক্ষতি হিসাব করুন। হিসাবের সূত্র ও করণীয়: /faq/cod-return-loss-hisab। Facebook Pixel-এ ফেক Purchase থাকলে /ads-roas-calculator দিয়ে পরিষ্কার ROAS দেখুন।',
                ],
                [
                    'q' => 'ইংরেজি পেজ আছে কি?',
                    'a' => 'হ্যাঁ — ইংরেজি: /en/bd-fraud-checker। ধাপে ধাপে বাংলা গাইড: /ki-vabe-fake-order-atkabo।',
                ],
            ],
        ],

        'fake_order_protection' => [
            'title' => 'কিভাবে ফেক অর্ডার আটকাবো — চেক, OTP ও ব্লক | WooEasyLife',
            'description' => 'কিভাবে ফেক অর্ডার আটকাবো? কুরিয়ার হিস্টোরি চেক, চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট দিয়ে COD পার্সেল রিটার্ন কমান — WooCommerce ও Facebook পেজ সেলারদের জন্য।',
            'canonical_path' => '/fake-order-protection',
            'prerender_h1' => 'কিভাবে ফেক অর্ডার আটকাবো — Fake Order Protection',
            'prerender_lead' => 'ফ্রড চেক + OTP + ডুপ্লিকেট ব্লক + ব্ল্যাকলিস্ট — মাল্টি-লেয়ার প্রোটেকশনে ফেক অর্ডার ও রিটার্ন লস কমান। WooCommerce ও Facebook পেজ COD সেলারদের জন্য।',
            'hreflang_paths' => [
                'bn-BD' => '/fake-order-protection',
                'en' => '/en/fake-order-protection',
                'x-default' => '/fake-order-protection',
            ],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'ফেক অর্ডার প্রোটেকশন', 'path' => '/fake-order-protection'],
            ],
            'faqs' => [
                [
                    'q' => 'ফেক অর্ডার কেন হয়?',
                    'a' => 'COD ও Facebook পেজ ব্যবসায় অনেকে ভুল নম্বর বা মজার অর্ডার দেয়। পার্সেল পাঠালে রিটার্ন চার্জ, প্যাকেজিং ও সময় বিক্রেতার ঘাড়ে পড়ে।',
                ],
                [
                    'q' => 'কিভাবে ফেক অর্ডার আটকাবো?',
                    'a' => 'মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি চেক করুন, চেকআউট OTP চালু করুন, ডুপ্লিকেট ও ব্ল্যাকলিস্ট রাখুন। ধাপে ধাপে গাইড: /ki-vabe-fake-order-atkabo',
                ],
                [
                    'q' => 'WooEasyLife কীভাবে ফেক অর্ডার আটকায়?',
                    'a' => 'কুরিয়ার হিস্টোরি, চেকআউট OTP, ডুপ্লিকেট অর্ডার ব্লক, ফোন/ইমেইল/ডিভাইস ব্লক ও দৈনিক অর্ডার সীমা — একসাথে মাল্টি-লেয়ার প্রোটেকশন।',
                ],
                [
                    'q' => 'শুধু ফ্রড চেক করলেই কি যথেষ্ট?',
                    'a' => 'চেক শুরু। বারবার ফেক প্যাটার্ন আটকাতে OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট লাগে। ফ্রি চেক: /bd-fraud-checker',
                ],
                [
                    'q' => 'চেকআউট OTP কী করে?',
                    'a' => 'চেকআউটে ফোন নম্বরে OTP পাঠিয়ে যাচাই করে — ভুয়া/ভুল নম্বরে অর্ডার আগেই আটকে।',
                ],
                [
                    'q' => 'ডুপ্লিকেট অর্ডার ব্লক কেন দরকার?',
                    'a' => 'একই কাস্টমার/নম্বর থেকে বারবার অর্ডার এলে অতিরিক্ত পার্সেল ও রিটার্ন বাড়ে। ডুপ্লিকেট ভ্যালিডেশন সেগুলো আটকায়।',
                ],
                [
                    'q' => 'রিটার্ন লস কত বাঁচতে পারে?',
                    'a' => 'দিনে কয়েকটি ফেক অর্ডার আটকালেই মাসে হাজার হাজার টাকা বাঁচে। আনুমানিক হিসাব: /return-loss-calculator',
                ],
                [
                    'q' => 'ফেক অর্ডার কি Ads ROAS নষ্ট করে?',
                    'a' => 'হ্যাঁ — Pixel-এ ফেক Purchase গেলে রিপোর্টেড ROAS ফোলে। আসল ROAS দেখতে /ads-roas-calculator ব্যবহার করুন।',
                ],
                [
                    'q' => 'কীভাবে শুরু করব?',
                    'a' => 'প্রথমে /bd-fraud-checker দিয়ে ফ্রি চেক করুন, তারপর /pricing থেকে ট্রায়াল নিয়ে OTP ও ব্লক চালু করুন।',
                ],
                [
                    'q' => 'ইংরেজি ভার্সন আছে কি?',
                    'a' => 'হ্যাঁ — /en/fake-order-protection। বাংলা পেজ: /fake-order-protection।',
                ],
            ],
        ],

        'return_loss_calculator' => [
            'title' => 'রিটার্ন লস ক্যালকুলেটর — মাসে কত টাকা বাঁচবে | WooEasyLife',
            'description' => 'ফ্রি রিটার্ন লস ক্যালকুলেটর। দৈনিক অর্ডার, রিটার্ন/ক্যানসেল রেট ও প্রতি রিটার্নের খরচ দিয়ে মাসিক COD রিটার্ন লস ও সম্ভাব্য সাশ্রয় হিসাব করুন — WooCommerce ও Facebook পেজ সেলারদের জন্য।',
            'canonical_path' => '/return-loss-calculator',
            'prerender_h1' => 'রিটার্ন লস কমিয়ে মাসে কত টাকা বাঁচাতে পারবেন?',
            'prerender_lead' => 'দৈনিক অর্ডার, রিটার্ন/ক্যানসেল রেট ও প্রতি রিটার্নে গড় খরচ দিন — মাসিক লস ও সম্ভাব্য সাশ্রয় তাৎক্ষণিক দেখুন। বাংলাদেশি COD সেলারদের জন্য ফ্রি শিক্ষামূলক টুল।',
            'hreflang_paths' => [
                'bn-BD' => '/return-loss-calculator',
                'en' => '/en/return-loss-calculator',
                'x-default' => '/return-loss-calculator',
            ],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'রিটার্ন লস ক্যালকুলেটর', 'path' => '/return-loss-calculator'],
            ],
            'faqs' => [
                [
                    'q' => 'রিটার্ন লস ক্যালকুলেটর কী?',
                    'a' => 'এটি একটি ফ্রি টুল যেখানে দৈনিক অর্ডার সংখ্যা, রিটার্ন/ক্যানসেল রেট ও প্রতি রিটার্নের গড় খরচ দিয়ে মাসিক রিটার্ন লস ও সম্ভাব্য সাশ্রয় হিসাব করা যায়।',
                ],
                [
                    'q' => 'প্রতি রিটার্নে গড় খরচে কী কী ধরব?',
                    'a' => 'সাধারণত কুরিয়ার রিটার্ন চার্জ, প্যাকেজিং ও সময়ের আনুমানিক খরচ যোগ করুন। অনেক COD স্টোরে এটি প্রায় ৳১৫০–৩০০ বা তার বেশি হতে পারে — আপনার স্টোর অনুযায়ী স্লাইডার মিলিয়ে নিন।',
                ],
                [
                    'q' => 'হিসাব কীভাবে কাজ করে?',
                    'a' => 'মাসিক অর্ডার ≈ দৈনিক অর্ডার × ৩০। মাসিক রিটার্ন ≈ মাসিক অর্ডার × রিটার্ন রেট। মাসিক লস ≈ মাসিক রিটার্ন × প্রতি রিটার্নের খরচ।',
                ],
                [
                    'q' => 'WooEasyLife কীভাবে রিটার্ন লস কমায়?',
                    'a' => 'অর্ডার কনফার্মের আগে কুরিয়ার হিস্টোরি/ফ্রড চেক, চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট দিয়ে ঝুঁকিপূর্ণ অর্ডার আটকায় — কম ফেক পার্সেল মানে কম রিটার্ন লস।',
                ],
                [
                    'q' => 'হিসাব কি আনুমানিক?',
                    'a' => 'হ্যাঁ — শিক্ষামূলক অনুমান। আসল সাশ্রয় আপনার স্টোরের অর্ডার কোয়ালিটি, কুরিয়ার রেট ও কতটা প্রোটেকশন চালু আছে তার ওপর নির্ভর করে।',
                ],
                [
                    'q' => 'ফ্রি ফ্রড চেক কোথায় করব?',
                    'a' => '/bd-fraud-checker পেজে মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি চেক করুন। পূর্ণ প্রোটেকশন: /fake-order-protection ও /pricing।',
                ],
                [
                    'q' => 'রিটার্ন লস আর Ads ROAS-এর সম্পর্ক কী?',
                    'a' => 'রিটার্ন অপারেশন লস দেখায়; ফেক Purchase অ্যাড ROAS ফুলায়। দুটোই দেখতে /ads-roas-calculator ব্যবহার করুন।',
                ],
                [
                    'q' => 'ইংরেজি ভার্সন আছে কি?',
                    'a' => 'হ্যাঁ — /en/return-loss-calculator। বাংলা পেজ: /return-loss-calculator।',
                ],
            ],
        ],

        'courier_charge_calculator' => [
            'title' => 'কুরিয়ার চার্জ ক্যালকুলেটর — Pathao · Steadfast · RedX | WooEasyLife',
            'description' => 'ফ্রি কুরিয়ার চার্জ ক্যালকুলেটর। ঢাকা/সাবআরবান/বাইরের জোন ও ওজন দিয়ে Pathao, Steadfast, RedX আনুমানিক ডেলিভারি চার্জ তুলনা করুন — বাংলাদেশি COD মার্চেন্টদের জন্য।',
            'canonical_path' => '/courier-charge-calculator',
            'prerender_h1' => 'Pathao · Steadfast · RedX — ডেলিভারি চার্জ আনুমানিক হিসাব',
            'prerender_lead' => 'জোন ও পার্সেল ওজন দিন — তিন কুরিয়ারের আনুমানিক চার্জ একসাথে তুলনা করুন। COD ফিসহ হিসাব। চূড়ান্ত চার্জ প্যানেলে যাচাই করুন।',
            'hreflang_paths' => [
                'bn-BD' => '/courier-charge-calculator',
                'en' => '/en/courier-charge-calculator',
                'x-default' => '/courier-charge-calculator',
            ],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'কুরিয়ার চার্জ ক্যালকুলেটর', 'path' => '/courier-charge-calculator'],
            ],
            'faqs' => [
                [
                    'q' => 'এই চার্জ কি অফিসিয়াল?',
                    'a' => 'Steadfast রেট প্রতিদিন তাদের পাবলিক প্রাইসিং API (steadfast.com.bd/pricing) থেকে সিঙ্ক হয়। Pathao-এর পাবলিক ক্যালকুলেটর লগইন ছাড়া পাওয়া যায় না — মার্চেন্ট API কনফিগ থাকলে স্যাম্পল আপডেট হয়, নইলে আনুমানিক। RedX আনুমানিক। চূড়ান্ত চার্জ কুরিয়ার প্যানেলে যাচাই করুন।',
                ],
                [
                    'q' => 'রেট কত ঘন ঘন আপডেট হয়?',
                    'a' => 'সার্ভারে প্রতিদিন স্বয়ংক্রিয় সিঙ্ক চলে (Asia/Dhaka)। ম্যানুয়ালি: php artisan courier:sync-public-rates',
                ],
                [
                    'q' => 'COD ফি কীভাবে হিসাব হয়?',
                    'a' => 'উদাহরণ হিসাবে COD অ্যামাউন্টের প্রায় ১% COD ফি ধরা হয়েছে। আপনার কুরিয়ার প্ল্যান ভিন্ন হলে স্লাইডার দিয়ে মিলিয়ে নিন।',
                ],
                [
                    'q' => 'WooEasyLife কীভাবে সাহায্য করে?',
                    'a' => 'অর্ডার কনফার্ম হলেই Pathao/Steadfast/RedX-এ অটো এন্ট্রি হয় — প্যানেলে বারবার চার্জ ও ঠিকানা টাইপ করতে হয় না। বিস্তারিত: /courier-auto-entry',
                ],
                [
                    'q' => 'কোন জোন বেছে নেব?',
                    'a' => 'ঢাকার ভিতর, সাবআরবান/আশেপাশে, বা ঢাকার বাইরে — ডেলিভারি ঠিকানা অনুযায়ী বেছে নিন। ভুল জোন দিলে চার্জ ভুল দেখাবে।',
                ],
                [
                    'q' => 'সস্তা কুরিয়ারই কি বেস্ট?',
                    'a' => 'সবসময় নয়। ফেক অর্ডার/রিটার্ন বেশি হলে সস্তা চার্জও লসে যায়। আগে /bd-fraud-checker দিয়ে চেক করুন এবং /return-loss-calculator দিয়ে রিটার্ন লস মাপুন।',
                ],
                [
                    'q' => 'ওজন কীভাবে নেব?',
                    'a' => 'প্যাকিংসহ আনুমানিক কেজি দিন। ওজন বাড়লে অতিরিক্ত কেজির চার্জ যোগ হয় — কম দেখালে পরে বিল বাড়তে পারে।',
                ],
                [
                    'q' => 'অ্যাড খরচের সাথে কীভাবে মিলিয়ে দেখব?',
                    'a' => 'ডেলিভারি চার্জ + রিটার্ন লস + অ্যাড স্পেন্ড একসাথে দেখুন। /ads-roas-calculator দিয়ে আসল ROAS এবং /return-loss-calculator দিয়ে মাসিক রিটার্ন লস হিসাব করুন।',
                ],
                [
                    'q' => 'ইংরেজি ভার্সন আছে?',
                    'a' => 'হ্যাঁ — /en/courier-charge-calculator। বাংলা পেজ: /courier-charge-calculator।',
                ],
            ],
        ],

        'ads_roas_calculator' => [
            'title' => 'Facebook Ads ROAS ক্যালকুলেটর — ফেক Purchase বাদ | WooEasyLife',
            'description' => 'ফ্রি Facebook Ads ROAS ক্যালকুলেটর। অ্যাড স্পেন্ড, Pixel Purchase ও ফেক/ক্যানসেল রেট দিয়ে রিপোর্টেড vs আসল ROAS হিসাব করুন — পিক্সেল প্রোটেকশনের গুরুত্ব বুঝুন।',
            'canonical_path' => '/ads-roas-calculator',
            'prerender_h1' => 'ফেক Purchase বাদ দিয়ে আসল Facebook Ads ROAS কত?',
            'prerender_lead' => 'Pixel-এ যাওয়া Purchase ≠ কনফার্মড অর্ডার। স্পেন্ড ও ফেক রেট দিয়ে আসল ROAS দেখুন — COD সেলারদের জন্য শিক্ষামূলক হিসাব।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Ads ROAS ক্যালকুলেটর', 'path' => '/ads-roas-calculator'],
            ],
            'hreflang_paths' => [
                'bn-BD' => '/ads-roas-calculator',
                'en' => '/en/ads-roas-calculator',
                'x-default' => '/ads-roas-calculator',
            ],
            'faqs' => [
                [
                    'q' => 'রিপোর্টেড ROAS আর আসল ROAS-এর পার্থক্য কী?',
                    'a' => 'রিপোর্টেড ROAS Pixel-এর সব Purchase ধরে। আসল ROAS শুধু কনফার্মড/ডেলিভার্ড অর্ডার ধরে — ফেক ও ক্যানসেল বাদ।',
                ],
                [
                    'q' => 'ফেক Purchase কেন সমস্যা?',
                    'a' => 'Facebook ভুল অডিয়েন্সে অপটিমাইজ করে, অ্যাড বাজেট নষ্ট হয়। WooEasyLife পিক্সেল প্রোটেকশন শুধু কনফার্মড অর্ডার পাঠায়।',
                ],
                [
                    'q' => 'হিসাব কি আনুমানিক?',
                    'a' => 'হ্যাঁ — শিক্ষামূলক অনুমান। আপনার Attribution সেটিং ও ডেলিভারি রেট অনুযায়ী সংখ্যা ভিন্ন হতে পারে।',
                ],
                [
                    'q' => 'কোন ইনপুট দিলে হিসাব সঠিক হবে?',
                    'a' => 'মাসিক Ads স্পেন্ড, Pixel Purchase সংখ্যা, আনুমানিক ফেক/ক্যানসেল/রিটার্ন রেট এবং গড় অর্ডার ভ্যালু (AOV) দিন। স্লাইডার সরালেই রিপোর্টেড ও আসল ROAS আপডেট হয়।',
                ],
                [
                    'q' => 'আসল ROAS বাড়াতে কী করব?',
                    'a' => 'প্রথমে /bd-fraud-checker ও /fake-order-protection দিয়ে ফেক অর্ডার কমান, তারপর শুধু কনফার্মড Purchase Pixel-এ পাঠান। রিটার্ন লস দেখতে /return-loss-calculator ব্যবহার করুন।',
                ],
                [
                    'q' => 'এই ক্যালকুলেটর কি Meta Ads Manager-এর বিকল্প?',
                    'a' => 'না — এটি শিক্ষামূলক তুলনা টুল। ক্যাম্পেইন ম্যানেজমেন্ট Meta-তেই থাকবে; এখানে ফেক Purchase বাদ দিয়ে আসল লাভ বুঝবেন।',
                ],
                [
                    'q' => 'কত ঘন ঘন ROAS হিসাব করব?',
                    'a' => 'অন্তত সাপ্তাহিক। স্পেন্ড, Purchase ও রিটার্ন রেট বদলালে স্লাইডার আপডেট করে আসল ROAS দেখুন — মাসে একবারে স্কেল সিদ্ধান্ত ঝুঁকিপূর্ণ।',
                ],
                [
                    'q' => 'AOV কীভাবে নেব?',
                    'a' => 'একই মাসের মোট অর্ডার ভ্যালু ÷ অর্ডার সংখ্যা (বা Pixel Purchase)। প্রমোশন/ফ্রি গিফট থাকলে গড় আলাদা করে নিন, নাহলে ROAS ফুলে দেখাবে।',
                ],
                [
                    'q' => 'ইংরেজি ভার্সন আছে কি?',
                    'a' => 'হ্যাঁ — /en/ads-roas-calculator। বাংলা পেজ: /ads-roas-calculator।',
                ],
            ],
        ],

        'ki_vabe_fake_order_atkabo' => [
            'title' => 'কিভাবে ফেক অর্ডার আটকাবো গাইড ২০২৬ — চেক→OTP→ব্লক',
            'description' => 'কিভাবে ফেক অর্ডার আটকাবো: কুরিয়ার হিস্টোরি চেক, OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট দিয়ে BD COD রিটার্ন ও অ্যাড লস কমান—ধাপে ধাপে গাইড।',
            'canonical_path' => '/ki-vabe-fake-order-atkabo',
            'og_type' => 'article',
            'og_image' => '/images/seo/cluster/fraud-layers.jpg',
            'prerender_h1' => 'কিভাবে ফেক অর্ডার আটকাবো — ধাপে ধাপে সম্পূর্ণ গাইড',
            'prerender_lead' => 'ফ্রড চেক → কল/OTP → ব্ল্যাকলিস্ট → কনফার্ম → কুরিয়ার অটো এন্ট্রি। টুল-শুধু নয়—পূর্ণ WooCommerce COD সুরক্ষা।',
            'hreflang_paths' => [
                'bn-BD' => '/ki-vabe-fake-order-atkabo',
                'en' => '/en/ki-vabe-fake-order-atkabo',
                'x-default' => '/ki-vabe-fake-order-atkabo',
            ],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'কিভাবে ফেক অর্ডার আটকাবো', 'path' => '/ki-vabe-fake-order-atkabo'],
            ],
            'faqs' => [
                [
                    'q' => 'কিভাবে ফেক অর্ডার আটকাবো সবচেয়ে দ্রুত?',
                    'a' => 'সবচেয়ে দ্রুত উপায়: অর্ডার কনফার্ম বা কুরিয়ার বুকিংয়ের আগে কাস্টমারের মোবাইল নম্বর দিয়ে /bd-fraud-checker বা /fake-customer-check চালান। Pathao/Steadfast/RedX হিস্টোরি ও সাকসেস রেট দেখে সবুজ হলে কনফার্ম করুন; হলুদ/লাল বা খুব কম সাকসেস হলে অন্ধ শিপ না করে কল, OTP বা হোল্ড নিন। একবারের চেক যথেষ্ট নয়—বারবার ফেক আটকাতে /fake-order-protection-এ চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চালু রাখুন। মোবাইল থেকে দ্রুত approve/hold: /woocommerce-mobile-app।',
                ],
                [
                    'q' => 'শুধু ফ্রি টুল কি যথেষ্ট?',
                    'a' => 'ফ্রি কুরিয়ার-হিস্টোরি চেক শুরুর স্তর—প্রতিদিনের নতুন অর্ডারে ঝুঁকি দেখতে যথেষ্ট। কিন্তু একই নম্বর/ডিভাইস বারবার অর্ডার দিলে বা ভুয়া নম্বর টাইপ করলে শুধু ম্যানুয়াল চেক স্টাফ ক্লান্ত করে আবার অন্ধ শিপ শুরু হয়। দীর্ঘমেয়াদে চেকআউট OTP, ডুপ্লিকেট ভ্যালিডেশন, ফোন/ডিভাইস ব্ল্যাকলিস্ট ও দৈনিক অর্ডার লিমিট লাগে—সব /fake-order-protection ও /pricing ট্রায়ালে একসাথে পাওয়া যায়। কনফার্মের পর ভুল কমাতে /courier-auto-entry ব্যবহার করুন।',
                ],
                [
                    'q' => 'সবুজ/হলুদ/লাল জোনে কী করব?',
                    'a' => 'সবুজ (উচ্চ সাকসেস): দ্রুত কনফার্ম করে /courier-auto-entry দিয়ে বুকিং করুন। হলুদ (মাঝারি): ফোন-কনফার্ম বা কন্ডিশনাল OTP নিন, ঠিকানা পরিষ্কার করুন, তারপর approve। লাল (কম সাকসেস/বারবার রিটার্ন): অগ্রিম ডেলিভারি চার্জ ছাড়া শিপ করবেন না—হোল্ড বা বাতিল রাখুন এবং নোট লিখে রাখুন। জোন রুল ও OTP সেটআপের বিস্তারিত: /customer-verification। একটা খারাপ রেকর্ড মানেই সবসময় ফেক নয়—তাই হিস্টোরি + ছোট কল একসাথে সবচেয়ে নিরাপদ।',
                ],
                [
                    'q' => 'কনফার্মের পর কী করব?',
                    'a' => 'ভেরিফায়েড অর্ডারে /courier-auto-entry দিয়ে Pathao/Steadfast/RedX পার্সেল বুক করুন—ম্যানুয়াল প্যানেলে কপি-পেস্ট ভুল ও সময় কমে। ট্র্যাকিং আইডি সেভ হলে /woocommerce-notifications দিয়ে In Transit ও বিশেষ করে Out for Delivery মেসেজ পাঠান; এতে কাস্টমার COD টাকা রেডি রাখে ও রাইডার কল এক্সপেক্ট করে, “কাস্টমার নট এভেলেবল” রিটার্ন কমে। কুরিয়ার সেটআপ: /pathao-courier-guide, /steadfast-integration, /redx-courier-guide।',
                ],
                [
                    'q' => 'রিটার্ন লস ও অ্যাড ROAS কীভাবে মাপব?',
                    'a' => 'প্রথমে /return-loss-calculator-এ দৈনিক অর্ডার, রিটার্ন রেট ও প্রতি রিটার্নের খরচ বসিয়ে মাসিক ৳ লস দেখুন—বেসলাইন ছাড়া “কমেছে কি?” বোঝা যায় না। ফেক Purchase Pixel-এ গেলে Facebook ROAS ফুলে দেখায়; ডেলিভারি-অ্যাডজাস্টেড হিসাবের জন্য /ads-roas-calculator ব্যবহার করুন। RTS কমানোর পূর্ণ প্লেবুক: /cod-return-reduction। অ্যাড বাজেট বাড়ানোর আগে ফেক ফিল্টার স্থিতিশীল করুন: /facebook-ads-for-woocommerce।',
                ],
                [
                    'q' => 'ইংরেজি ভার্সন আছে কি?',
                    'a' => 'হ্যাঁ—সম্পূর্ণ ইংরেজি ধাপে ধাপে গাইড: /en/ki-vabe-fake-order-atkabo (hreflang-যুক্ত মিরর)। সম্পর্কিত ইংরেজি টুল ও প্রোডাক্ট পেজ: /en/bd-fraud-checker, /en/fake-order-protection, /en/customer-verification। বাংলা হাব কনটেক্সট চাইলে /woocommerce-bangladesh থেকে শুরু করুন।',
                ],
            ],
        ],

        'fake_customer_check' => [
            'title' => 'Fake Customer Check BD ২০২৬ — নম্বর দিয়ে যাচাই',
            'description' => 'Fake customer check BD: মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি, সাকসেস রেট ও রিটার্ন প্যাটার্ন দেখে COD কনফার্মের আগে ফেক কাস্টমার আটকান।',
            'canonical_path' => '/fake-customer-check',
            'og_type' => 'article',
            'og_image' => '/images/seo/cluster/fraud-layers.jpg',
            'prerender_h1' => 'Fake Customer Check — অর্ডার কনফার্মের আগে কাস্টমার যাচাই',
            'prerender_lead' => 'মোবাইল নম্বর দিয়ে Pathao/Steadfast/RedX হিস্টোরি দেখুন—সবুজ হলে কনফার্ম, হলুদ/লালে কল বা হোল্ড। ফ্রি চেক, অ্যাকাউন্ট লাগে না।',
            'hreflang_paths' => [
                'bn-BD' => '/fake-customer-check',
                'en' => '/en/fake-customer-check',
                'x-default' => '/fake-customer-check',
            ],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Fake Customer Check', 'path' => '/fake-customer-check'],
            ],
            'faqs' => [
                [
                    'q' => 'Fake customer check কী?',
                    'a' => 'Fake customer check মানে অর্ডার কনফার্ম বা পার্সেল বুকিংয়ের আগে কাস্টমারের মোবাইল নম্বর দিয়ে আগের কুরিয়ার ডেলিভারি সাকসেস, রিটার্ন ও ক্যানসেল হিস্টোরি দেখা। এটি কোনো আইনি বা চূড়ান্ত “fraud verdict” নয়—ডেটা-ভিত্তিক risk signal, যার সঙ্গে কল, ঠিকানা ও OTP যাচাই মিলিয়ে সিদ্ধান্ত নিতে হয়। পূর্ণ checker: /bd-fraud-checker। Fraud score ব্যাখ্যা: /faq/customer-fraud-score-ki।',
                ],
                [
                    'q' => 'এটি কি ফ্রি? অ্যাকাউন্ট লাগে?',
                    'a' => 'হ্যাঁ—ল্যান্ডিং পেজে অ্যাকাউন্ট ছাড়াই প্রতিদিন সীমিত সংখ্যক ফ্রি চেক করা যায়, তাই ঝুঁকি ছাড়াই ওয়ার্কফ্লো টেস্ট করতে পারেন। বেশি ভলিউম, অটোমেটেড চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চাইলে /pricing থেকে ট্রায়াল বা সাবস্ক্রিপশন নিন। মোবাইলে পুশসহ চালাতে: /woocommerce-mobile-app। শুধু-চেকার টুলের সাথে তুলনা: /fraudbd-alternative।',
                ],
                [
                    'q' => 'সাকসেস রেট কম হলে কী করব?',
                    'a' => 'কম সাকসেস বা বারবার রিটার্ন দেখলে অন্ধ শিপ করবেন না—প্রথমে ফোন-কনফার্ম, ঠিকানা যাচাই, প্রয়োজনে OTP বা অগ্রিম ডেলিভারি চার্জ নিন; যাচাই না হলে হোল্ড রাখুন এবং অর্ডার নোটে কারণ লিখুন। একটি রেকর্ড মানেই fraud verdict নয়। রেট বোঝা: /faq/courier-success-rate-kivabe-bujhbo। করণীয় SOP: /faq/success-rate-kom-hole-ki-korbo।',
                ],
                [
                    'q' => 'Fake customer check আর BD Fraud Checker আলাদা?',
                    'a' => 'মূল ইঞ্জিন একই—কুরিয়ার হিস্টোরি দিয়ে কাস্টমার কোয়ালিটি যাচাই। এই পেজ “কনফার্মের আগে কাস্টমার যাচাই / fake customer check” কিওয়ার্ড ও ওয়ার্কফ্লোতে ফোকাস করে; /bd-fraud-checker পূর্ণ Courier Fraud Checker BD টুল UI ও বিস্তারিত গাইড। দুটোই ফ্রি চেক দেয়। পরবর্তী ধাপ: /fake-order-protection, /courier-auto-entry ও /ki-vabe-fake-order-atkabo।',
                ],
                [
                    'q' => 'শুধু চেক করলেই কি ফেক অর্ডার বন্ধ?',
                    'a' => 'চেক শুরুর গেটকিপার—প্রতি অর্ডারে ঝুঁকির সিগন্যাল দেখায়। কিন্তু একই প্যাটার্ন বারবার আটকাতে checkout OTP, duplicate block ও blacklist লাগে। কখন OTP নেবেন: /faq/cod-order-otp-kokhon। duplicate COD order: /faq/duplicate-cod-order-block। customer blacklist: /faq/woocommerce-customer-blacklist। পূর্ণ সুরক্ষা ও প্ল্যান: /pricing।',
                ],
                [
                    'q' => 'কোন কুরিয়ারের হিস্টোরি দেখা যায়?',
                    'a' => 'Pathao, Steadfast, RedX সহ WooEasyLife-এ সাপোর্টেড প্রধান কুরিয়ার নেটওয়ার্কের ডেলিভারি ও রিটার্ন রেকর্ড দেখা যায়—এক নম্বরে একাধিক সিগন্যাল। কীভাবে history পড়বেন: /faq/customer-delivery-history-check। স্টোরে API কানেক্ট করে auto-booking চালাতে: /pathao-courier-guide, /steadfast-integration ও /redx-courier-guide।',
                ],
                [
                    'q' => 'রিটার্ন লস ও Ads ROAS কীভাবে মাপব?',
                    'a' => 'মাসিক রিটার্ন খরচ মাপতে /return-loss-calculator-এ দৈনিক অর্ডার, রিটার্ন রেট ও প্রতি রিটার্নের খরচ দিন; সূত্র ও উদাহরণ: /faq/cod-return-loss-hisab। ফেক Purchase ROAS ফোলালে /ads-roas-calculator দিয়ে পরিষ্কার হিসাব দেখুন। সব সংশ্লিষ্ট প্রশ্ন: /faq।',
                ],
                [
                    'q' => 'ইংরেজি ভার্সন আছে কি?',
                    'a' => 'হ্যাঁ—/en/fake-customer-check (hreflang-পেয়ার)। সম্পর্কিত ইংরেজি টুল: /en/bd-fraud-checker, /en/fake-order-protection, /en/fraudbd-alternative। ইংরেজি হোম: /en। বাংলা হোম: /।',
                ],
            ],
        ],

        'bd_courier_ratio_checker' => [
            'title' => 'BD Courier Ratio Checker ২০২৬ — সাকসেস রেট ফ্রি',
            'description' => 'BD Courier Order Ratio Checker: মোবাইল নম্বর দিয়ে Pathao/Steadfast/RedX সাকসেস রেট ও রিটার্ন রেশিও ফ্রি চেক করুন—COD কনফার্মের আগে কোয়ালিটি দেখুন।',
            'canonical_path' => '/bd-courier-ratio-checker',
            'og_type' => 'article',
            'og_image' => '/images/seo/cluster/fraud-layers.jpg',
            'prerender_h1' => 'BD Courier Order Ratio Checker — ডেলিভারি সাকসেস রেট',
            'prerender_lead' => 'মোবাইল নম্বর দিয়ে সাকসেস রেট ও রিটার্ন রেশিও দেখুন। কম রেটে অন্ধ শিপ করবেন না—Pathao, Steadfast, RedX।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'BD Courier Ratio Checker', 'path' => '/bd-courier-ratio-checker'],
            ],
            'faqs' => [
                [
                    'q' => 'Order ratio checker কী দেখায়?',
                    'a' => 'ডেলিভারি সাকসেস বনাম রিটার্ন/ক্যানসেল হিস্টোরি—কাস্টমার কোয়ালিটি বোঝার জন্য। Pathao, Steadfast, RedX সিগন্যাল একসাথে আসে। টুল অন্ধ অটো-শিপ করে না। পূর্ণ UI: /bd-fraud-checker। জোন রুল: /customer-verification।',
                ],
                [
                    'q' => 'Courier number check online কিভাবে?',
                    'a' => 'এই পেজের ফ্রি টুলে 01XXXXXXXXX দিন—অ্যাকাউন্ট লাগে না। সাকসেস রেশিও পড়ে সবুজ কনফার্ম, হলুদ কল/OTP, লাল হোল্ড। বিকল্প: /bd-fraud-checker, /courier-checker, /fake-customer-check।',
                ],
                [
                    'q' => 'সাকসেস রেট কম হলে কী করব?',
                    'a' => 'অন্ধ শিপ করবেন না—ফোন-কনফার্ম, OTP বা অগ্রিম চার্জ নিন, নাহলে হোল্ড। লস মাপুন: /return-loss-calculator। প্রোটেকশন: /fake-order-protection। গাইড: /ki-vabe-fake-order-atkabo।',
                ],
                [
                    'q' => 'Ratio Checker আর Fraud Checker আলাদা?',
                    'a' => 'ইঞ্জিন একই। এই পেজ “order ratio / success rate” সার্চে ফোকাস; /bd-fraud-checker পূর্ণ টুল ল্যান্ডিং। কুরিয়ার-নির্দিষ্ট: /pathao-fraud-check, /steadfast-fraud-check, /redx-fraud-check।',
                ],
                [
                    'q' => 'এটি কি ফ্রি?',
                    'a' => 'হ্যাঁ—দৈনিক সীমিত ফ্রি চেক অ্যাকাউন্ট ছাড়াই। বেশি ভলিউম ও OTP/ব্ল্যাকলিস্ট চাইলে /pricing। মোবাইল: /woocommerce-mobile-app।',
                ],
            ],
        ],

        'fake_order_check' => [
            'title' => 'Fake Order Check BD ২০২৬ — নম্বর দিয়ে যাচাই',
            'description' => 'Fake Order Check BD: মোবাইল নম্বর দিয়ে Pathao/Steadfast/RedX হিস্টোরি ও সাকসেস রেট ফ্রি চেক করুন—COD কনফার্মের আগে ফেক অর্ডার এড়ান।',
            'canonical_path' => '/fake-order-check',
            'og_type' => 'article',
            'og_image' => '/images/seo/cluster/fraud-layers.jpg',
            'prerender_h1' => 'Fake Order Check BD — ফ্রি কুরিয়ার ফ্রড চেকার',
            'prerender_lead' => 'মোবাইল নম্বর দিয়ে কুরিয়ার হিস্টোরি দেখে পার্সেল পাঠানোর আগে সিদ্ধান্ত নিন—Pathao, Steadfast, RedX।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Fake Order Check', 'path' => '/fake-order-check'],
            ],
            'faqs' => [
                [
                    'q' => 'Fake order check আর fraud checker একই?',
                    'a' => 'প্রায় একই ইঞ্জিন—নম্বর দিয়ে কুরিয়ার হিস্টোরি দেখে ফেক/ঝুঁকি চিনা। এই পেজ “fake order check” কিওয়ার্ডে; /bd-fraud-checker পূর্ণ টুল UI; /fake-customer-check কাস্টমার-যাচাই অ্যাঙ্গেল; /courier-checker নম্বর-চেক অ্যাঙ্গেল।',
                ],
                [
                    'q' => 'কীভাবে ফেক অর্ডার চেক করব?',
                    'a' => 'নিচের টুলে নম্বর দিন → সাকসেস রেট পড়ুন → সবুজ কনফার্ম, হলুদ কল/OTP, লাল হোল্ড। ধাপে ধাপে: /ki-vabe-fake-order-atkabo। জোন: /customer-verification।',
                ],
                [
                    'q' => 'শুধু চেক কি যথেষ্ট?',
                    'a' => 'চেক শুরু। বারবার ফেক আটকাতে OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট লাগে (/fake-order-protection)। কনফার্মের পর /courier-auto-entry। লস: /return-loss-calculator।',
                ],
                [
                    'q' => 'এটি কি ফ্রি?',
                    'a' => 'হ্যাঁ—অ্যাকাউন্ট ছাড়াই দৈনিক সীমিত ফ্রি চেক। ট্রায়াল: /pricing। তুলনা: /fraudbd-alternative।',
                ],
                [
                    'q' => 'কোন কুরিয়ার সাপোর্ট করে?',
                    'a' => 'Pathao, Steadfast, RedX সহ সাপোর্টেড নেটওয়ার্ক। গাইড: /pathao-courier-guide, /steadfast-integration, /redx-courier-guide।',
                ],
            ],
        ],

        'courier_checker' => [
            'title' => 'Courier Checker BD ২০২৬ — নম্বর চেক অনলাইন ফ্রি',
            'description' => 'Courier Checker BD: মোবাইল নম্বর দিয়ে Pathao/Steadfast/RedX কুরিয়ার হিস্টোরি ও সাকসেস রেট অনলাইন ফ্রি চেক করুন—COD কনফার্মের আগে ফেক অর্ডার এড়ান।',
            'canonical_path' => '/courier-checker',
            'og_type' => 'article',
            'og_image' => '/images/seo/cluster/fraud-layers.jpg',
            'prerender_h1' => 'Courier Checker BD — অনলাইন ফ্রি কুরিয়ার নম্বর চেক',
            'prerender_lead' => 'Courier number check online: বাংলাদেশি মোবাইল দিন, Pathao/Steadfast/RedX হিস্টোরি ও কোয়ালিটি দেখুন, তারপরই কনফার্ম বা হোল্ড সিদ্ধান্ত নিন।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Courier Checker', 'path' => '/courier-checker'],
            ],
            'faqs' => [
                [
                    'q' => 'Courier Checker BD কী?',
                    'a' => 'Courier Checker BD মানে কাস্টমারের মোবাইল নম্বর দিয়ে বাংলাদেশি কুরিয়ার নেটওয়ার্কে আগের ডেলিভারি, রিটার্ন ও ক্যানসেল হিস্টোরি অনলাইনে যাচাই করা। COD ও Facebook/WooCommerce সেলাররা পার্সেল বুকিংয়ের আগে কোয়ালিটি দেখে ফেক/ঝুঁকিপূর্ণ অর্ডার কমায়। টুল সিগন্যাল দেয়—অন্ধ অটো-শিপ করে না। একই ইঞ্জিনের পূর্ণ UI: /bd-fraud-checker। কাস্টমার-ফোকাসড ওয়ার্কফ্লো: /fake-customer-check। ধাপে ধাপে: /ki-vabe-fake-order-atkabo।',
                ],
                [
                    'q' => 'Courier number check online কীভাবে করব?',
                    'a' => 'এই পেজের ফ্রি টুলে 01XXXXXXXXX ফরম্যাটে বাংলাদেশি মোবাইল নম্বর দিন—অ্যাকাউন্ট লাগে না। Pathao, Steadfast, RedX সহ সাপোর্টেড কুরিয়ারের হিস্টোরি ও সাকসেস রেট দেখুন। সবুজে কনফার্ম, হলুদে কল/OTP, লালে হোল্ড বা অগ্রিম চার্জ। বিকল্প UI: /bd-fraud-checker। রেশিও-ফোকাস: /bd-courier-ratio-checker। কুরিয়ার-নির্দিষ্ট: /pathao-fraud-check, /steadfast-fraud-check, /redx-fraud-check।',
                ],
                [
                    'q' => 'এটি কি ফ্রি? দৈনিক লিমিট আছে?',
                    'a' => 'হ্যাঁ—ল্যান্ডিংয়ে সীমিত ফ্রি চেক অ্যাকাউন্ট ছাড়াই চলে, তাই ঝুঁকি ছাড়াই SOP টেস্ট করতে পারেন। বেশি ভলিউম, চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট চাইলে /pricing থেকে ট্রায়াল নিন এবং /fake-order-protection চালু করুন। মোবাইলে পুশসহ: /woocommerce-mobile-app। চেকার-শুধু টুলের সাথে তুলনা: /fraudbd-alternative।',
                ],
                [
                    'q' => 'Courier Checker আর Fake Order Check / Fraud Checker আলাদা?',
                    'a' => 'মূল ইঞ্জিন একই—কুরিয়ার হিস্টোরি দিয়ে ঝুঁকি দেখা। এই পেজ “courier checker / courier number check online” সার্চ ইন্টেন্টে ফোকাস করে; /fake-order-check ফেক অর্ডার কিওয়ার্ডে; /fake-customer-check কনফার্মের আগে কাস্টমার যাচাইতে; /bd-fraud-checker পূর্ণ টুল ল্যান্ডিং। যেকোনো পেজ থেকে ফ্রি চেক চালাতে পারেন। পরের ধাপ: /fake-order-protection ও /courier-auto-entry।',
                ],
                [
                    'q' => 'হিস্টোরি খারাপ হলে কী করব?',
                    'a' => 'কম সাকসেস বা বারবার রিটার্ন দেখলে অন্ধ শিপ করবেন না—ফোন-কনফার্ম, ঠিকানা যাচাই, প্রয়োজনে OTP বা অগ্রিম ডেলিভারি চার্জ নিন, নাহলে হোল্ড/বাতিল ও নোটে কারণ লিখুন। জোন রুল: /customer-verification। মাসিক লস মাপুন: /return-loss-calculator। পূর্ণ COD প্লেবুক: /cod-return-reduction। গাইড: /ki-vabe-fake-order-atkabo।',
                ],
                [
                    'q' => 'কোন কুরিয়ার সাপোর্ট করে?',
                    'a' => 'Pathao, Steadfast, RedX সহ WooEasyLife-এ সাপোর্টেড প্রধান কুরিয়ার নেটওয়ার্কের ডেলিভারি/রিটার্ন রেকর্ড দেখা যায়—এক নম্বরে একাধিক সিগন্যাল, তাই শুধু এক কুরিয়ারের প্যানেলে লগইন করে খোঁজার দরকার কমে। স্টোরে API কানেক্ট করে অটো বুকিং চালাতে: /pathao-courier-guide, /steadfast-integration, /redx-courier-guide। কনফার্মের পর দৈনন্দিন এন্ট্রি: /courier-auto-entry। ট্র্যাকিং মেসেজ: /woocommerce-notifications।',
                ],
                [
                    'q' => 'শুধু Courier Checker দিয়েই কি ফেক অর্ডার বন্ধ?',
                    'a' => 'চেক শুরুর গেটকিপার—প্রতি অর্ডারে কোয়ালিটি দেখায়। কিন্তু একই নম্বর/ডিভাইস বারবার অর্ডার দিলে শুধু ম্যানুয়াল চেক স্টাফ ক্লান্ত করে আবার অন্ধ শিপ শুরু হয়। দীর্ঘমেয়াদে চেকআউট OTP, ডুপ্লিকেট ব্লক ও ব্ল্যাকলিস্ট লাগে (/fake-order-protection)। কনফার্মের পর অটো এন্ট্রি + Out-for-Delivery নোটিফিকেশন বৈধ রিটার্নও কমায়। অ্যাড ROAS পরিষ্কার রাখতে: /ads-roas-calculator ও /facebook-ads-for-woocommerce।',
                ],
                [
                    'q' => 'রিটার্ন লস ও Ads ROAS কীভাবে মাপব?',
                    'a' => 'প্রথমে /return-loss-calculator-এ দৈনিক অর্ডার, রিটার্ন রেট ও প্রতি রিটার্নের খরচ বসিয়ে মাসিক ৳ লস দেখুন—বেসলাইন ছাড়া “চেক কাজ করছে কি?” বোঝা যায় না। ফেক Purchase Pixel-এ গেলে Facebook ROAS ফুলে দেখায়; ডেলিভারি-অ্যাডজাস্টেড হিসাব: /ads-roas-calculator। অ্যাড স্কেলের আগে Courier Checker + প্রোটেকশন স্থিতিশীল করুন—গাইড: /facebook-ads-for-woocommerce। হাব: /woocommerce-bangladesh।',
                ],
            ],
        ],

        'courier_auto_entry' => [
            'title' => 'কুরিয়ার অটো এন্ট্রি — Pathao Steadfast RedX WooCommerce | WooEasyLife',
            'description' => 'WooCommerce কুরিয়ার অটো এন্ট্রি: অর্ডার কনফার্ম হলেই Pathao, Steadfast, RedX-এ পার্সেল এন্ট্রি। স্ট্যাটাস সিঙ্ক, SMS ও পার্সেল নোট হিস্ট্রি — COD সেলারদের দিনে ৩+ ঘণ্টা বাঁচান।',
            'canonical_path' => '/courier-auto-entry',
            'prerender_h1' => 'কুরিয়ার অটো এন্ট্রি — Pathao, Steadfast, RedX',
            'prerender_lead' => 'অর্ডার কনফার্ম হলেই কুরিয়ার প্যানেলে অটো এন্ট্রি। ম্যানুয়াল কপি-পেস্ট বাদ দিয়ে দিনে ৩+ ঘণ্টা সময় বাঁচান — WooCommerce ও Facebook পেজ COD সেলারদের জন্য।',
            'hreflang_paths' => [
                'bn-BD' => '/courier-auto-entry',
                'en' => '/en/courier-auto-entry',
                'x-default' => '/courier-auto-entry',
            ],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'কুরিয়ার অটো এন্ট্রি', 'path' => '/courier-auto-entry'],
            ],
            'faqs' => [
                [
                    'q' => 'কুরিয়ার অটো এন্ট্রি কী?',
                    'a' => 'অর্ডার কনফার্ম করার সাথে সাথে নাম, ফোন, ঠিকানা ও COD অ্যামাউন্ট Pathao, Steadfast বা RedX প্যানেলে স্বয়ংক্রিয়ভাবে এন্ট্রি হয় — ম্যানুয়াল কপি-পেস্ট লাগে না।',
                ],
                [
                    'q' => 'কোন কুরিয়ার সাপোর্ট করে?',
                    'a' => 'Pathao, Steadfast, RedX সহ ইন্টিগ্রেটেড কুরিয়ার — এক WooEasyLife ড্যাশবোর্ড থেকে এন্ট্রি, স্ট্যাটাস সিঙ্ক ও SMS।',
                ],
                [
                    'q' => 'পার্সেল নোট হিস্ট্রি কী?',
                    'a' => 'Steadfast পার্সেলের নোট ও হিস্ট্রি WooEasyLife থেকেই দেখা যায়, এবং মার্চেন্ট নোট আপডেট করা যায় — আলাদা কুরিয়ার প্যানেলে লগইন করতে হয় না।',
                ],
                [
                    'q' => 'ফ্রড চেক করার পর কি অটো এন্ট্রি হয়?',
                    'a' => 'আপনি কনফার্ম করলেই এন্ট্রি হয়। আগে /bd-fraud-checker দিয়ে ঝুঁকি দেখে নিন, তারপর কনফার্ম — নিরাপদ ও দ্রুত ওয়ার্কফ্লো।',
                ],
                [
                    'q' => 'ম্যানুয়াল এন্ট্রিতে কত সময় নষ্ট হয়?',
                    'a' => 'প্রতি অর্ডারে কয়েক মিনিট × দিনে কয়েক ডজন অর্ডার = ঘণ্টার পর ঘণ্টা। WooEasyLife অটো এন্ট্রিতে দিনে ৩+ ঘণ্টা স্টাফ সময় বাঁচানো সম্ভব।',
                ],
                [
                    'q' => 'কুরিয়ার স্ট্যাটাস কীভাবে আপডেট হয়?',
                    'a' => 'কুরিয়ার অটো স্ট্যাটাস সিঙ্ক WooCommerce অর্ডারে ডেলিভারি/রিটার্ন আপডেট নিয়ে আসে — আলাদা প্যানেলে বারবার চেক করতে হয় না।',
                ],
                [
                    'q' => 'কাস্টমারকে SMS যায় কি?',
                    'a' => 'হ্যাঁ — অর্ডার ও ডেলিভারি আপডেট SMS পাঠানো যায়, যাতে «পার্সেল কোথায়?» কল কমে এবং প্রফেশনাল ইম্প্রেশন তৈরি হয়।',
                ],
                [
                    'q' => 'চার্জ আগে জানতে চাইলে কী করব?',
                    'a' => '/courier-charge-calculator পেজে জোন ও ওজন দিয়ে Pathao, Steadfast, RedX আনুমানিক চার্জ তুলনা করুন।',
                ],
                [
                    'q' => 'কীভাবে শুরু করব?',
                    'a' => '/pricing থেকে ফ্রি ট্রায়াল বা সাবস্ক্রিপশন নিন, কুরিয়ার অ্যাকাউন্ট কানেক্ট করুন — তারপর কনফার্ম = অটো এন্ট্রি।',
                ],
                [
                    'q' => 'ইংরেজি ভার্সন আছে কি?',
                    'a' => 'হ্যাঁ — /en/courier-auto-entry। বাংলা পেজ: /courier-auto-entry।',
                ],
            ],
        ],

        'fraudbd_alternative' => [
            'title' => 'FraudBD Alternative — WooEasyLife BD Fraud Checker + WooCommerce',
            'description' => 'FraudBD বা শুধু-টুল ফ্রড চেকারের বিকল্প খুঁজছেন? WooEasyLife-এ ফ্রি BD fraud checker ছাড়াও ফেক অর্ডার প্রোটেকশন, কুরিয়ার অটো এন্ট্রি ও মোবাইল অ্যাপ একসাথে।',
            'canonical_path' => '/fraudbd-alternative',
            'prerender_h1' => 'FraudBD Alternative — টুলের বদলে পূর্ণ WooCommerce প্ল্যাটফর্ম',
            'prerender_lead' => 'শুধু ফ্রড হিস্টোরি নয় — WooEasyLife-এ চেক, ব্লক, কুরিয়ার অটো এন্ট্রি ও অর্ডার রিকভারি এক প্ল্যাটফর্মে।',
            'hreflang_paths' => [
                'bn-BD' => '/fraudbd-alternative',
                'en' => '/en/fraudbd-alternative',
                'x-default' => '/fraudbd-alternative',
            ],
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'FraudBD Alternative', 'path' => '/fraudbd-alternative'],
            ],
            'faqs' => [
                [
                    'q' => 'FraudBD আর WooEasyLife-এর মূল পার্থক্য কী?',
                    'a' => 'FraudBD মূলত কুরিয়ার ফ্রড/হিস্টোরি চেক টুল—নম্বর দিলে সিগন্যাল দেখায়। WooEasyLife FraudBD Alternative হিসেবে সেই চেক রাখে (/bd-fraud-checker, /fake-customer-check), সাথে WooCommerce ফেক অর্ডার প্রোটেকশন (OTP, ডুপ্লিকেট, ব্ল্যাকলিস্ট), কুরিয়ার অটো এন্ট্রি, হারানো অর্ডার রিকভারি ও মোবাইল অ্যাপ—চেক→ব্লক→কনফার্ম→বুকিং এক ফ্লোতে। বিস্তারিত প্রোটেকশন: /fake-order-protection। অটো এন্ট্রি: /courier-auto-entry।',
                ],
                [
                    'q' => 'আমি কি ফ্রি চেক করতে পারব?',
                    'a' => 'হ্যাঁ। /bd-fraud-checker বা /fake-customer-check পেজে অ্যাকাউন্ট ছাড়াই দৈনিক সীমিত ফ্রি চেক করা যায়—ওয়ার্কফ্লো টেস্ট করে তারপর সাবস্ক্রিপশন নিতে পারেন। বেশি ভলিউম ও অটোমেটেড প্রোটেকশন চাইলে /pricing দেখুন।',
                ],
                [
                    'q' => 'কখন WooEasyLife বেছে নেবেন?',
                    'a' => 'যখন শুধু নম্বর চেক নয়, পুরো COD অপারেশন চান: কনফার্মের আগে যাচাই, চেকআউট সুরক্ষা, কুরিয়ার বুকিং ও মোবাইল থেকে approve/hold। দিনে কয়েক ডজন COD অর্ডার এলে শুধু-চেকার ট্যাব ধীর ও ভুলপ্রবণ হয়। ধাপে ধাপে স্ট্যাক: /ki-vabe-fake-order-atkabo। অপস হাব: /woocommerce-bangladesh।',
                ],
                [
                    'q' => 'চেকার টুল কি ফেলে দিতে হবে?',
                    'a' => 'না—চাইলে পুরনো চেকার ট্যাব রাখতে পারেন। কিন্তু চেকআউট OTP, অটো এন্ট্রি ও রিকভারি WooEasyLife-এ চালালে দৈনন্দিন কাজ সাধারণত এক জায়গায় সরে যায়, ট্যাব-হপিং কমে। সুইচ প্ল্যান এই পেজের “৭ দিন” সেকশনে আছে; শুরু: /pricing।',
                ],
                [
                    'q' => 'কুরিয়ার অটো এন্ট্রি কোনগুলো সাপোর্ট করে?',
                    'a' => 'Pathao, Steadfast ও RedX—অর্ডার কনফার্ম হলে পার্সেল তথ্য অটো যায়, ম্যানুয়াল প্যানেল কপি-পেস্ট কমে। বিস্তারিত: /courier-auto-entry। সেটআপ গাইড: /pathao-courier-guide, /steadfast-integration, /redx-courier-guide। ট্র্যাকিং মেসেজ: /woocommerce-notifications।',
                ],
                [
                    'q' => 'রিটার্ন লস কীভাবে মাপব?',
                    'a' => '/return-loss-calculator দিয়ে দৈনিক অর্ডার, রিটার্ন রেট ও প্রতি রিটার্নের খরচ বসিয়ে মাসিক লস হিসাব করুন। শুধু টুলের দাম নয়—রিটার্ন চার্জই বড় লস। ফেক Purchase ROAS ফোলালে /ads-roas-calculator ব্যবহার করুন। COD প্লেবুক: /cod-return-reduction।',
                ],
                [
                    'q' => 'কীভাবে শুরু করব?',
                    'a' => 'প্রথমে /bd-fraud-checker বা /fake-customer-check দিয়ে ফ্রি চেক টেস্ট করুন এবং /return-loss-calculator বেসলাইন নিন। তারপর /pricing থেকে ট্রায়াল, WooCommerce কানেক্ট, /fake-order-protection চালু, কুরিয়ার API কানেক্ট করে /courier-auto-entry চালান। পূর্ণ গাইড: /ki-vabe-fake-order-atkabo।',
                ],
                [
                    'q' => 'ইংরেজি ভার্সন আছে?',
                    'a' => 'হ্যাঁ—/en/fraudbd-alternative (hreflang-পেয়ার)। সম্পর্কিত ইংরেজি: /en/bd-fraud-checker, /en/fake-customer-check, /en/fake-order-protection। ইংরেজি হোম: /en। বাংলা হোম: /।',
                ],
            ],
        ],

        'pathao_fraud_check' => [
            'title' => 'Pathao Fraud Check BD ২০২৬ — ফ্রি হিস্টোরি চেক',
            'description' => 'Pathao fraud check BD: মোবাইল নম্বর দিয়ে Pathao সহ Steadfast/RedX হিস্টোরি ও সাকসেস রেট ফ্রি চেক করুন—COD কনফার্মের আগে ফেক অর্ডার কমান।',
            'canonical_path' => '/pathao-fraud-check',
            'og_type' => 'article',
            'og_image' => '/images/seo/cluster/fraud-layers.jpg',
            'prerender_h1' => 'Pathao Fraud Check বাংলাদেশ',
            'prerender_lead' => 'ফোন নম্বর দিয়ে Pathao কুরিয়ার হিস্টোরি যাচাই করুন। অর্ডার কনফার্মের আগে ফেক অর্ডারের ঝুঁকি কমান।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Pathao Fraud Check', 'path' => '/pathao-fraud-check'],
            ],
            'faqs' => [
                [
                    'q' => 'Pathao fraud check কীভাবে কাজ করে?',
                    'a' => 'কাস্টমারের মোবাইল নম্বর দিলে WooEasyLife Pathao সহ সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন হিস্টোরি দেখায়। সাকসেস রেট কম হলে কনফার্মের আগে কল/OTP বা হোল্ড নিন। পূর্ণ টুল: /bd-fraud-checker।',
                ],
                [
                    'q' => 'শুধু Pathao দেখা যায় নাকি অন্য কুরিয়ারও?',
                    'a' => 'একই টুলে Pathao, Steadfast, RedX রেকর্ড একসাথে। এই পেজ Pathao সার্চ ইন্টেন্টে ফোকাস। অন্য: /steadfast-fraud-check, /redx-fraud-check। API গাইড: /pathao-courier-guide।',
                ],
                [
                    'q' => 'এটি কি ফ্রি?',
                    'a' => 'হ্যাঁ—অ্যাকাউন্ট ছাড়াই দৈনিক সীমিত ফ্রি চেক। OTP/ব্ল্যাকলিস্ট চাইলে /fake-order-protection ও /pricing।',
                ],
                [
                    'q' => 'চেকের পর কী করব?',
                    'a' => 'সবুজে কনফার্ম করে /courier-auto-entry; হলুদ/লালে /customer-verification রুল। লস: /return-loss-calculator। গাইড: /ki-vabe-fake-order-atkabo।',
                ],
            ],
        ],

        'steadfast_fraud_check' => [
            'title' => 'Steadfast Fraud Check BD ২০২৬ — ফ্রি হিস্টোরি',
            'description' => 'Steadfast fraud check BD: মোবাইল নম্বর দিয়ে Steadfast সহ Pathao/RedX হিস্টোরি ফ্রি চেক করুন—COD ফেক অর্ডার কমান।',
            'canonical_path' => '/steadfast-fraud-check',
            'og_type' => 'article',
            'og_image' => '/images/seo/cluster/fraud-layers.jpg',
            'prerender_h1' => 'Steadfast Fraud Check বাংলাদেশ',
            'prerender_lead' => 'ফোন নম্বর দিয়ে Steadfast কুরিয়ার হিস্টোরি যাচাই করুন। অর্ডার কনফার্মের আগে ফেক অর্ডারের ঝুঁকি কমান।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'Steadfast Fraud Check', 'path' => '/steadfast-fraud-check'],
            ],
            'faqs' => [
                [
                    'q' => 'Steadfast fraud check কীভাবে কাজ করে?',
                    'a' => 'মোবাইল নম্বর ইনপুট করলে Steadfast সহ সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন রেকর্ড দেখা যায়। কম সাকসেসে অন্ধ শিপ করবেন না। পূর্ণ টুল: /bd-fraud-checker।',
                ],
                [
                    'q' => 'Pathao ও Steadfast দুটোই কি চেক করা যায়?',
                    'a' => 'হ্যাঁ—একই BD fraud checker টুলে একাধিক কুরিয়ার। Pathao ফোকাস: /pathao-fraud-check। RedX: /redx-fraud-check। API: /steadfast-integration।',
                ],
                [
                    'q' => 'ফ্রি কিভাবে চেক করব?',
                    'a' => 'এই পেজে বা /bd-fraud-checker দিয়ে নম্বর চেক করুন—অ্যাকাউন্ট লাগে না। প্রোটেকশন: /fake-order-protection।',
                ],
                [
                    'q' => 'চেকের পর কী করব?',
                    'a' => 'কনফার্ম হলে /courier-auto-entry; ঝুঁকিতে /customer-verification। লস: /return-loss-calculator। গাইড: /ki-vabe-fake-order-atkabo।',
                ],
            ],
        ],

        'redx_fraud_check' => [
            'title' => 'RedX Fraud Check BD ২০২৬ — ফ্রি হিস্টোরি চেক',
            'description' => 'RedX fraud check BD: মোবাইল নম্বর দিয়ে RedX সহ Pathao/Steadfast হিস্টোরি ফ্রি চেক করুন—COD ফেক অর্ডার কমান।',
            'canonical_path' => '/redx-fraud-check',
            'og_type' => 'article',
            'og_image' => '/images/seo/cluster/fraud-layers.jpg',
            'prerender_h1' => 'RedX Fraud Check বাংলাদেশ',
            'prerender_lead' => 'ফোন নম্বর দিয়ে RedX কুরিয়ার হিস্টোরি যাচাই করুন। অর্ডার কনফার্মের আগে ফেক অর্ডারের ঝুঁকি কমান।',
            'breadcrumbs' => [
                ['name' => 'হোম', 'path' => '/'],
                ['name' => 'RedX Fraud Check', 'path' => '/redx-fraud-check'],
            ],
            'faqs' => [
                [
                    'q' => 'RedX fraud check কীভাবে কাজ করে?',
                    'a' => 'কাস্টমারের ফোন নম্বর দিলে RedX সহ সাপোর্টেড কুরিয়ারের ডেলিভারি ও রিটার্ন হিস্টোরি দেখা যায়। সাকসেস রেট দিয়ে কনফার্ম/হোল্ড সিদ্ধান্ত নিন। পূর্ণ টুল: /bd-fraud-checker।',
                ],
                [
                    'q' => 'অন্যান্য কুরিয়ারও কি একসাথে দেখা যায়?',
                    'a' => 'হ্যাঁ—Pathao, Steadfast ও RedX একই টুলে। Pathao: /pathao-fraud-check। Steadfast: /steadfast-fraud-check। API: /redx-courier-guide।',
                ],
                [
                    'q' => 'এটি কি ফ্রি?',
                    'a' => 'হ্যাঁ—ল্যান্ডিংয়ে অ্যাকাউন্ট ছাড়াই দৈনিক সীমিত ফ্রি চেক। ট্রায়াল: /pricing। প্রোটেকশন: /fake-order-protection।',
                ],
                [
                    'q' => 'চেকের পর কী করব?',
                    'a' => 'কনফার্ম → /courier-auto-entry; ঝুঁকি → /customer-verification। লস: /return-loss-calculator। গাইড: /ki-vabe-fake-order-atkabo।',
                ],
            ],
        ],

        'blog_index' => [
            'title' => 'WooEasyLife ব্লগ — ফেক অর্ডার, ফ্রড চেক ও COD টিপস',
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
                [
                    'q' => 'How do free checks work?',
                    'a' => 'Limited free daily searches work on this page without an account. Enter a mobile number to see supported courier history. Full protection needs a subscription — see /pricing.',
                ],
                [
                    'q' => 'Which couriers are supported?',
                    'a' => 'Pathao, Steadfast, RedX and other couriers supported inside WooEasyLife for history checks and auto-entry.',
                ],
                [
                    'q' => 'How do I stop fake orders long term?',
                    'a' => 'Check history first, then enable OTP, duplicate blocks, and blacklists. Guide: /en/fake-order-protection',
                ],
                [
                    'q' => 'Is there a Bangla version?',
                    'a' => 'Yes — the main home page at /',
                ],
            ],
        ],

        'en_bd_fraud_checker' => [
            'title' => 'Free Courier Fraud Checker BD — Phone History Check | WooEasyLife',
            'description' => 'Free Courier Fraud Checker BD. Check Pathao, Steadfast, RedX delivery history and success rate by mobile number. Stop fake COD orders before you ship.',
            'canonical_path' => '/en/bd-fraud-checker',
            'html_lang' => 'en',
            'prerender_h1' => 'Free Courier Fraud Checker BD — delivery history by phone',
            'prerender_lead' => 'Verify customer courier history and success rate before confirming COD orders. Pathao, Steadfast, RedX — built for Bangladesh WooCommerce & Facebook sellers.',
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
                [
                    'q' => 'Is a checker enough by itself?',
                    'a' => 'It is the first layer. Full protection needs checkout OTP, duplicate blocking, and blacklists — see /en/fake-order-protection. Courier auto-entry speeds confirmed orders.',
                ],
                [
                    'q' => 'What if success rate is low?',
                    'a' => 'Call to verify the address, request advance payment, or hold shipping. Repeat bad patterns belong on a blacklist.',
                ],
                [
                    'q' => 'Is this a FraudBD alternative?',
                    'a' => 'You get free courier history checks plus WooCommerce protection and courier automation. Compare at /fraudbd-alternative.',
                ],
                [
                    'q' => 'How do I measure return and ad loss?',
                    'a' => 'Use /en/return-loss-calculator for monthly COD return cost and /en/ads-roas-calculator for reported vs real ROAS after fake purchases.',
                ],
                [
                    'q' => 'Is there a Bangla version?',
                    'a' => 'Yes — Bangla: /bd-fraud-checker. Step-by-step Bangla guide: /ki-vabe-fake-order-atkabo.',
                ],
                [
                    'q' => 'Do I need an account for free checks?',
                    'a' => 'No. Limited free daily searches work without signup. Subscribe via /pricing for full protection features.',
                ],
            ],
        ],

        'en_ki_vabe_fake_order_atkabo' => [
            'title' => 'How to Stop Fake Orders Guide 2026 — Check→OTP→Block',
            'description' => 'How to stop fake COD orders in Bangladesh: courier history checks, OTP, duplicate blocks, and blacklists—step-by-step for WooCommerce and Facebook sellers.',
            'canonical_path' => '/en/ki-vabe-fake-order-atkabo',
            'html_lang' => 'en',
            'og_type' => 'article',
            'og_image' => '/images/seo/cluster/fraud-layers.jpg',
            'prerender_h1' => 'How to stop fake orders — step-by-step guide',
            'prerender_lead' => 'Fraud check → call/OTP → blacklist → confirm → courier auto entry. Not checker-only—full WooCommerce COD protection.',
            'hreflang_paths' => [
                'bn-BD' => '/ki-vabe-fake-order-atkabo',
                'en' => '/en/ki-vabe-fake-order-atkabo',
                'x-default' => '/ki-vabe-fake-order-atkabo',
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/en'],
                ['name' => 'How to stop fake orders', 'path' => '/en/ki-vabe-fake-order-atkabo'],
            ],
            'faqs' => [
                [
                    'q' => 'How do I stop fake orders fastest?',
                    'a' => 'Fastest path: before you confirm or book courier, run the customer mobile on /en/bd-fraud-checker or /en/fake-customer-check. Read Pathao/Steadfast/RedX history and success rate—confirm green orders; on yellow/red or very low success, do not blind-ship—call, require OTP, or hold. One-off checks are not enough long term: keep checkout OTP, duplicate blocks, and blacklists on via /en/fake-order-protection. Approve/hold from the road with /en/woocommerce-mobile-app. Bangla mirror: /ki-vabe-fake-order-atkabo. English home: /en.',
                ],
                [
                    'q' => 'Is a free checker enough?',
                    'a' => 'A free courier-history check is the first layer—great for screening every new COD order. But when the same number/device repeats or people type fake phones, manual checks alone burn staff time and shops slip back into shipping everything. Lasting control needs checkout OTP, duplicate validation, phone/device blacklists, and daily limits—available together in WooEasyLife via /en/fake-order-protection and /pricing. After confirm, cut panel typos with /en/courier-auto-entry.',
                ],
                [
                    'q' => 'What should I do in green/yellow/red zones?',
                    'a' => 'Green (high success): confirm quickly and book with /en/courier-auto-entry. Yellow (medium): call-confirm or conditional OTP, clean the address, then approve. Red (low success / serial returns): do not ship without an advance delivery fee—hold or cancel and leave a short order note. Full zone and OTP rules: /en/customer-verification. One bad record is not always “fake”—history plus a short call is safest.',
                ],
                [
                    'q' => 'What after I confirm?',
                    'a' => 'On verified orders, create the Pathao/Steadfast/RedX parcel with /en/courier-auto-entry so you skip merchant-panel copy-paste. Once the tracking ID is saved, send In Transit and especially Out for Delivery messages via /en/woocommerce-notifications—customers keep COD cash ready and expect rider calls, which cuts “customer unavailable” returns. Setup guides: /en/pathao-courier-guide, /en/steadfast-integration, /en/redx-courier-guide.',
                ],
                [
                    'q' => 'How do I measure return loss and ROAS?',
                    'a' => 'Baseline monthly ৳ loss with /en/return-loss-calculator (orders, return rate, cost per return)—without a baseline you cannot tell if filters work. Fake Purchase events inflate Meta ROAS; use /en/ads-roas-calculator for delivery-adjusted math. Full RTS playbook: /en/cod-return-reduction. Do not scale ad spend until fraud filters are stable—see /en/facebook-ads-for-woocommerce.',
                ],
                [
                    'q' => 'Is there a Bangla version?',
                    'a' => 'Yes—the full Bangla step guide is /ki-vabe-fake-order-atkabo (hreflang-paired with this page). Related English tools: /en/bd-fraud-checker, /en/fake-customer-check, /en/fake-order-protection, /en/customer-verification. For the broader ops hub start at /en/woocommerce-bangladesh. English home: /en.',
                ],
            ],
        ],

        'en_fake_customer_check' => [
            'title' => 'Fake Customer Check BD 2026 — Verify Before Confirm',
            'description' => 'Fake customer check BD: verify courier history, success rate, and return patterns by mobile number before confirming COD orders.',
            'canonical_path' => '/en/fake-customer-check',
            'html_lang' => 'en',
            'og_type' => 'article',
            'og_image' => '/images/seo/cluster/fraud-layers.jpg',
            'prerender_h1' => 'Fake Customer Check — verify before you confirm',
            'prerender_lead' => 'Check Pathao/Steadfast/RedX history by mobile—green confirm, yellow call/OTP, red hold. Free checks, no account required.',
            'hreflang_paths' => [
                'bn-BD' => '/fake-customer-check',
                'en' => '/en/fake-customer-check',
                'x-default' => '/fake-customer-check',
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/en'],
                ['name' => 'Fake Customer Check', 'path' => '/en/fake-customer-check'],
            ],
            'faqs' => [
                [
                    'q' => 'What is a fake customer check?',
                    'a' => 'It means verifying courier delivery success, return, and cancel history by mobile number before you confirm or book a COD parcel. Catching wrong numbers and serial refusers early saves return fees, packing, and ad CPA. The tool gives a signal—it does not blind auto-ship. Full tool UI: /en/bd-fraud-checker. Checkout OTP and blacklists: /en/fake-order-protection. Step guide: /en/ki-vabe-fake-order-atkabo. English home: /en.',
                ],
                [
                    'q' => 'Is it free? Do I need an account?',
                    'a' => 'Yes—limited free daily checks work on this landing page without signup, so you can test the workflow safely. For higher volume plus automated checkout OTP, duplicate blocks, and blacklists, start a trial on /pricing. Mobile push ops: /en/woocommerce-mobile-app. Checker-only comparison: /en/fraudbd-alternative.',
                ],
                [
                    'q' => 'What if success rate is low?',
                    'a' => 'Do not ship blindly—call to confirm, verify the address, require OTP or an advance delivery fee, or hold the order and leave a short note. Green/yellow/red zone rules: /en/customer-verification. Measure monthly ৳ loss with /en/return-loss-calculator. Full stack: /en/ki-vabe-fake-order-atkabo.',
                ],
                [
                    'q' => 'Is this different from BD Fraud Checker?',
                    'a' => 'Same courier-history engine. This page focuses on the “verify before confirm / fake customer check” intent and workflow; /en/bd-fraud-checker is the full Courier Fraud Checker BD tool UI and guide. Both offer free checks. Next steps: /en/fake-order-protection, /en/courier-auto-entry, and /en/ki-vabe-fake-order-atkabo.',
                ],
                [
                    'q' => 'Will checking alone stop fake orders?',
                    'a' => 'Checking is the gatekeeper—it shows risk per order. Stopping repeats needs checkout OTP, duplicate blocks, and blacklists (/en/fake-order-protection). After confirm, book with /en/courier-auto-entry and send Out-for-Delivery messages via /en/woocommerce-notifications to cut valid returns too. COD playbook: /en/cod-return-reduction. Platform comparison: /en/fraudbd-alternative.',
                ],
                [
                    'q' => 'Which courier histories can I see?',
                    'a' => 'Pathao, Steadfast, RedX and other couriers supported inside WooEasyLife—multiple signals on one number. Connect APIs for auto booking: /en/pathao-courier-guide, /en/steadfast-integration, /en/redx-courier-guide. Daily confirm→book flow: /en/courier-auto-entry.',
                ],
                [
                    'q' => 'How do I measure return loss and Ads ROAS?',
                    'a' => 'Monthly return cost: /en/return-loss-calculator (orders, return rate, cost per return). If fake Purchase events inflate Meta ROAS, use /en/ads-roas-calculator. Stabilize filters before scaling ads—guide: /en/facebook-ads-for-woocommerce.',
                ],
                [
                    'q' => 'Is there a Bangla version?',
                    'a' => 'Yes—/fake-customer-check (hreflang pair). Related Bangla tools: /bd-fraud-checker, /fake-order-protection, /fraudbd-alternative. Bangla home: /. English home: /en.',
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

        'en_fake_order_protection' => [
            'title' => 'How to Stop Fake Orders — Fake Order Protection | WooEasyLife',
            'description' => 'How to stop fake COD orders in Bangladesh: courier history checks, checkout OTP, duplicate blocks, and blacklists — for WooCommerce and Facebook page sellers.',
            'canonical_path' => '/en/fake-order-protection',
            'html_lang' => 'en',
            'prerender_h1' => 'How to stop fake orders — Fake Order Protection',
            'prerender_lead' => 'Fraud check + OTP + duplicate block + blacklist — multi-layer protection to cut fake COD orders and return loss. Built for Bangladesh WooCommerce and Facebook page sellers.',
            'hreflang_paths' => [
                'bn-BD' => '/fake-order-protection',
                'en' => '/en/fake-order-protection',
                'x-default' => '/fake-order-protection',
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/en'],
                ['name' => 'Fake Order Protection', 'path' => '/en/fake-order-protection'],
            ],
            'faqs' => [
                [
                    'q' => 'Why do fake orders happen?',
                    'a' => 'On COD and Facebook page sales, people often use wrong numbers or place joke orders. Once a parcel ships, return fees, packaging, and time hit the seller.',
                ],
                [
                    'q' => 'How do I stop fake orders?',
                    'a' => 'Check courier history by mobile, enable checkout OTP, and keep duplicate blocks plus blacklists on. Step guide: /en/ki-vabe-fake-order-atkabo. Bangla: /ki-vabe-fake-order-atkabo',
                ],
                [
                    'q' => 'How does WooEasyLife block fake orders?',
                    'a' => 'Courier history checks, checkout OTP, duplicate order blocks, phone/email/device blocks, and daily order limits — multi-layer protection in one platform.',
                ],
                [
                    'q' => 'Is a fraud check enough by itself?',
                    'a' => 'It is the first layer. Stopping repeat patterns needs OTP, duplicate blocks, and blacklists. Free checks: /en/bd-fraud-checker',
                ],
                [
                    'q' => 'What does checkout OTP do?',
                    'a' => 'It verifies the phone number at checkout with an OTP — fake or wrong numbers get blocked earlier.',
                ],
                [
                    'q' => 'Why block duplicate orders?',
                    'a' => 'Repeat orders from the same customer/number create extra parcels and returns. Duplicate validation stops that pattern.',
                ],
                [
                    'q' => 'How much return loss can I save?',
                    'a' => 'Blocking a few fake orders per day can save thousands of taka monthly. Estimate with /return-loss-calculator',
                ],
                [
                    'q' => 'Do fake orders hurt Ads ROAS?',
                    'a' => 'Yes — fake Pixel purchases inflate reported ROAS. Compare reported vs real ROAS on /en/ads-roas-calculator.',
                ],
                [
                    'q' => 'How do I get started?',
                    'a' => 'Run a free check on /en/bd-fraud-checker, then start a trial on /pricing and turn on OTP and blocks.',
                ],
                [
                    'q' => 'Is there a Bangla version?',
                    'a' => 'Yes — /fake-order-protection. English page: /en/fake-order-protection.',
                ],
            ],
        ],

        'en_courier_auto_entry' => [
            'title' => 'Courier Auto Entry — Pathao Steadfast RedX WooCommerce | WooEasyLife',
            'description' => 'WooCommerce courier auto-entry: confirm an order and Pathao, Steadfast, or RedX parcels enter automatically. Status sync, SMS, and parcel note history — save 3+ hours/day for COD sellers.',
            'canonical_path' => '/en/courier-auto-entry',
            'html_lang' => 'en',
            'prerender_h1' => 'Courier Auto Entry — Pathao, Steadfast, RedX',
            'prerender_lead' => 'Confirm an order and the courier panel fills automatically. Skip manual copy-paste and save 3+ hours a day — built for Bangladesh WooCommerce and Facebook page COD sellers.',
            'hreflang_paths' => [
                'bn-BD' => '/courier-auto-entry',
                'en' => '/en/courier-auto-entry',
                'x-default' => '/courier-auto-entry',
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/en'],
                ['name' => 'Courier Auto Entry', 'path' => '/en/courier-auto-entry'],
            ],
            'faqs' => [
                [
                    'q' => 'What is courier auto-entry?',
                    'a' => 'When you confirm an order, name, phone, address, and COD amount enter Pathao, Steadfast, or RedX automatically — no manual copy-paste.',
                ],
                [
                    'q' => 'Which couriers are supported?',
                    'a' => 'Integrated Pathao, Steadfast, and RedX — entry, status sync, and SMS from one WooEasyLife dashboard.',
                ],
                [
                    'q' => 'What is parcel note history?',
                    'a' => 'Steadfast parcel notes and history are visible in WooEasyLife, and merchant notes can be updated without logging into a separate courier panel.',
                ],
                [
                    'q' => 'Does auto-entry run after a fraud check?',
                    'a' => 'Entry runs when you confirm. Check risk first on /en/bd-fraud-checker, then confirm — a safer, faster workflow.',
                ],
                [
                    'q' => 'How much time does manual entry waste?',
                    'a' => 'A few minutes per order × dozens of daily orders = hours. WooEasyLife auto-entry can save 3+ staff hours per day.',
                ],
                [
                    'q' => 'How does courier status update?',
                    'a' => 'Auto status sync pulls delivery/return updates into the WooCommerce order — no repeated panel checks.',
                ],
                [
                    'q' => 'Do customers get SMS?',
                    'a' => 'Yes — order and delivery SMS can be sent so “where is my parcel?” calls drop and you look more professional.',
                ],
                [
                    'q' => 'How do I compare charges first?',
                    'a' => 'Use /courier-charge-calculator with zone and weight to compare Pathao, Steadfast, and RedX estimates.',
                ],
                [
                    'q' => 'How do I get started?',
                    'a' => 'Start a free trial or subscription on /pricing, connect courier accounts — then confirm = auto-entry.',
                ],
                [
                    'q' => 'Is there a Bangla version?',
                    'a' => 'Yes — /courier-auto-entry. English page: /en/courier-auto-entry.',
                ],
            ],
        ],

        'en_return_loss_calculator' => [
            'title' => 'Return Loss Calculator — How Much Can You Save Monthly? | WooEasyLife',
            'description' => 'Free COD return loss calculator. Enter daily orders, return/cancel rate, and cost per return to estimate monthly return loss and savings — for Bangladesh WooCommerce & Facebook sellers.',
            'canonical_path' => '/en/return-loss-calculator',
            'html_lang' => 'en',
            'prerender_h1' => 'How much can you save monthly by cutting return loss?',
            'prerender_lead' => 'Enter daily orders, return/cancel rate, and average cost per return — see monthly loss and estimated savings instantly. Free educational tool for Bangladesh COD sellers.',
            'hreflang_paths' => [
                'bn-BD' => '/return-loss-calculator',
                'en' => '/en/return-loss-calculator',
                'x-default' => '/return-loss-calculator',
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/en'],
                ['name' => 'Return Loss Calculator', 'path' => '/en/return-loss-calculator'],
            ],
            'faqs' => [
                [
                    'q' => 'What is the return loss calculator?',
                    'a' => 'A free tool that estimates monthly COD return loss and potential savings from daily orders, return/cancel rate, and average cost per return.',
                ],
                [
                    'q' => 'What should I include in cost per return?',
                    'a' => 'Usually courier return fees, packaging, and estimated staff time. Many COD stores land around ৳150–300 or more — match the slider to your store.',
                ],
                [
                    'q' => 'How does the math work?',
                    'a' => 'Monthly orders ≈ daily orders × 30. Monthly returns ≈ monthly orders × return rate. Monthly loss ≈ monthly returns × cost per return.',
                ],
                [
                    'q' => 'How does WooEasyLife reduce return loss?',
                    'a' => 'Before confirmation it uses courier history/fraud checks, checkout OTP, duplicate blocks, and blacklists — fewer fake parcels means less return loss.',
                ],
                [
                    'q' => 'Is this calculator exact?',
                    'a' => 'No — it is an educational estimate. Real savings depend on order quality, courier rates, and how much protection you enable.',
                ],
                [
                    'q' => 'Where do I run a free fraud check?',
                    'a' => 'Use /en/bd-fraud-checker. Full protection: /en/fake-order-protection and /pricing.',
                ],
                [
                    'q' => 'How does return loss relate to Ads ROAS?',
                    'a' => 'Return loss is operations cost; fake purchases inflate reported ROAS. Compare both with /en/ads-roas-calculator.',
                ],
                [
                    'q' => 'Is there a Bangla version?',
                    'a' => 'Yes — /return-loss-calculator. English page: /en/return-loss-calculator.',
                ],
            ],
        ],

        'en_ads_roas_calculator' => [
            'title' => 'Facebook Ads ROAS Calculator — Remove Fake Purchases | WooEasyLife',
            'description' => 'Free Facebook Ads ROAS calculator. Compare reported vs real ROAS using ad spend, Pixel purchases, and fake/cancel rate — built for Bangladesh COD & WooCommerce sellers.',
            'canonical_path' => '/en/ads-roas-calculator',
            'html_lang' => 'en',
            'prerender_h1' => 'What is your real Facebook Ads ROAS after removing fake purchases?',
            'prerender_lead' => 'Pixel Purchase ≠ confirmed order. Use spend and fake/cancel rate to see real ROAS — an educational tool for COD sellers.',
            'hreflang_paths' => [
                'bn-BD' => '/ads-roas-calculator',
                'en' => '/en/ads-roas-calculator',
                'x-default' => '/ads-roas-calculator',
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/en'],
                ['name' => 'Ads ROAS Calculator', 'path' => '/en/ads-roas-calculator'],
            ],
            'faqs' => [
                [
                    'q' => 'What is the difference between reported ROAS and real ROAS?',
                    'a' => 'Reported ROAS counts every Pixel Purchase. Real ROAS only counts confirmed/delivered orders after removing fake and cancelled purchases.',
                ],
                [
                    'q' => 'Why are fake Pixel purchases a problem?',
                    'a' => 'Facebook optimizes toward the wrong audience and wastes ad budget. WooEasyLife pixel protection helps send only confirmed purchases.',
                ],
                [
                    'q' => 'Is this calculator exact?',
                    'a' => 'No — it is an educational estimate. Attribution windows and delivery rates can change the numbers for your store.',
                ],
                [
                    'q' => 'Which inputs should I use?',
                    'a' => 'Monthly ad spend, Pixel Purchase count, estimated fake/cancel/return rate, and average order value (AOV). Moving the sliders updates reported vs real ROAS instantly.',
                ],
                [
                    'q' => 'How do I improve real ROAS?',
                    'a' => 'First reduce fake orders with /en/bd-fraud-checker and /fake-order-protection, then send only confirmed purchases to Pixel. Measure monthly return cost with /return-loss-calculator.',
                ],
                [
                    'q' => 'Does this replace Meta Ads Manager?',
                    'a' => 'No — it is a comparison tool. Campaign management stays in Meta; this page helps you judge real COD profit after fake purchases.',
                ],
                [
                    'q' => 'How often should I recalculate ROAS?',
                    'a' => 'At least weekly. Update spend, purchases, and return rate when they change — scaling once a month from reported ROAS alone is risky.',
                ],
                [
                    'q' => 'How should I pick AOV?',
                    'a' => 'Total order value ÷ order count for the same month (or Pixel Purchases). Adjust for promotions/free gifts or ROAS will look inflated.',
                ],
                [
                    'q' => 'Is there a Bangla version?',
                    'a' => 'Yes — /ads-roas-calculator. English page: /en/ads-roas-calculator.',
                ],
            ],
        ],

        'en_fraudbd_alternative' => [
            'title' => 'FraudBD Alternative — WooEasyLife BD Fraud Checker + WooCommerce',
            'description' => 'Looking for a FraudBD or checker-only alternative? WooEasyLife includes a free BD fraud checker plus fake-order protection, courier auto-entry, and a mobile app.',
            'canonical_path' => '/en/fraudbd-alternative',
            'html_lang' => 'en',
            'prerender_h1' => 'FraudBD Alternative — a full WooCommerce platform, not just a tool',
            'prerender_lead' => 'Not only fraud history — check, block, courier auto-entry, and order recovery in one platform.',
            'hreflang_paths' => [
                'bn-BD' => '/fraudbd-alternative',
                'en' => '/en/fraudbd-alternative',
                'x-default' => '/fraudbd-alternative',
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/en'],
                ['name' => 'FraudBD Alternative', 'path' => '/en/fraudbd-alternative'],
            ],
            'faqs' => [
                [
                    'q' => 'What is the main difference between FraudBD and WooEasyLife?',
                    'a' => 'FraudBD is mainly a courier fraud/history checker—enter a number, see a signal. As a FraudBD alternative, WooEasyLife keeps that check (/en/bd-fraud-checker, /en/fake-customer-check) and adds WooCommerce fake-order protection (OTP, duplicates, blacklists), courier auto-entry, missing-order recovery, and a mobile app—check → block → confirm → book in one flow. Protection details: /en/fake-order-protection. Auto-entry: /en/courier-auto-entry.',
                ],
                [
                    'q' => 'Can I check for free?',
                    'a' => 'Yes. Limited free daily checks work on /en/bd-fraud-checker or /en/fake-customer-check without an account—test the workflow, then subscribe if you need volume. Full plans: /pricing.',
                ],
                [
                    'q' => 'When should I choose WooEasyLife?',
                    'a' => 'When you want the full COD operation—not number checks alone: verify before confirm, checkout protection, courier booking, and approve/hold on mobile. With dozens of COD orders per day, a checker-only tab gets slow and error-prone. Step stack: /en/ki-vabe-fake-order-atkabo. Ops hub: /en/woocommerce-bangladesh.',
                ],
                [
                    'q' => 'Do I have to drop my checker tool?',
                    'a' => 'No—you can keep an old checker tab. Once OTP, auto-entry, and recovery run in WooEasyLife, daily work usually moves there and tab-hopping drops. Follow the 7-day switch plan on this page; start at /pricing.',
                ],
                [
                    'q' => 'Which couriers support auto-entry?',
                    'a' => 'Pathao, Steadfast, and RedX—parcel data pushes on confirm so you skip merchant-panel copy-paste. Details: /en/courier-auto-entry. Setup guides: /en/pathao-courier-guide, /en/steadfast-integration, /en/redx-courier-guide. Tracking messages: /en/woocommerce-notifications.',
                ],
                [
                    'q' => 'How do I measure return loss?',
                    'a' => 'Use /en/return-loss-calculator with daily orders, return rate, and cost per return. Tool price is small next to return fees. If fake Purchase events inflate Meta ROAS, use /en/ads-roas-calculator. COD playbook: /en/cod-return-reduction.',
                ],
                [
                    'q' => 'How do I get started?',
                    'a' => 'Test free checks on /en/bd-fraud-checker or /en/fake-customer-check and baseline /en/return-loss-calculator. Then start a trial from /pricing, connect WooCommerce, enable /en/fake-order-protection, connect courier APIs, and turn on /en/courier-auto-entry. Full guide: /en/ki-vabe-fake-order-atkabo.',
                ],
                [
                    'q' => 'Is there a Bangla version?',
                    'a' => 'Yes—/fraudbd-alternative (hreflang pair). Related Bangla: /bd-fraud-checker, /fake-customer-check, /fake-order-protection. Bangla home: /. English home: /en.',
                ],
            ],
        ],

        'en_courier_charge_calculator' => [
            'title' => 'Courier Charge Calculator — Pathao · Steadfast · RedX | WooEasyLife',
            'description' => 'Free courier charge calculator. Compare approximate Pathao, Steadfast, and RedX delivery charges by Dhaka / suburb / outside zone and weight — for Bangladesh COD merchants.',
            'canonical_path' => '/en/courier-charge-calculator',
            'html_lang' => 'en',
            'prerender_h1' => 'Pathao · Steadfast · RedX — estimate delivery charges',
            'prerender_lead' => 'Enter zone and parcel weight to compare approximate charges for three couriers. Includes COD fee. Verify final charges on the courier panel.',
            'hreflang_paths' => [
                'bn-BD' => '/courier-charge-calculator',
                'en' => '/en/courier-charge-calculator',
                'x-default' => '/courier-charge-calculator',
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'path' => '/en'],
                ['name' => 'Courier Charge Calculator', 'path' => '/en/courier-charge-calculator'],
            ],
            'faqs' => [
                [
                    'q' => 'Are these official rates?',
                    'a' => 'Steadfast rates sync daily from their public pricing API (steadfast.com.bd/pricing). Pathao’s public calculator is not available without login — merchant API can update samples, otherwise approximate. RedX is approximate. Always verify final charges on the courier panel.',
                ],
                [
                    'q' => 'How often do rates update?',
                    'a' => 'An automatic sync runs daily on the server (Asia/Dhaka). Manually: php artisan courier:sync-public-rates',
                ],
                [
                    'q' => 'How is COD fee calculated?',
                    'a' => 'As an example, about 1% of the COD amount is used as COD fee. If your courier plan differs, match the slider.',
                ],
                [
                    'q' => 'How does WooEasyLife help?',
                    'a' => 'When an order is confirmed, Pathao/Steadfast/RedX auto-entry runs — less repeated charge and address typing in panels. Details: /en/courier-auto-entry',
                ],
                [
                    'q' => 'Which zone should I pick?',
                    'a' => 'Inside Dhaka, suburb/nearby, or outside Dhaka — based on the delivery address. Wrong zone shows wrong charges.',
                ],
                [
                    'q' => 'Is the cheapest courier always best?',
                    'a' => 'Not always. High fake orders/returns can erase savings. Check with /en/bd-fraud-checker first and measure return loss on /return-loss-calculator.',
                ],
                [
                    'q' => 'How should I set weight?',
                    'a' => 'Enter approximate kg including packing. Extra kg adds charge — understating weight can raise the bill later.',
                ],
                [
                    'q' => 'How do I combine this with ad spend?',
                    'a' => 'Look at delivery charge + return loss + ad spend together. Use /en/ads-roas-calculator for real ROAS and /return-loss-calculator for monthly return loss.',
                ],
                [
                    'q' => 'Is there a Bangla version?',
                    'a' => 'Yes — /courier-charge-calculator. English page: /en/courier-charge-calculator.',
                ],
            ],
        ],
    ],

    'sitemap' => [
        'paths' => [
            ['path' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['path' => '/pricing', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/bd-fraud-checker', 'priority' => '0.95', 'changefreq' => 'weekly'],
            ['path' => '/faq', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/faq/courier-success-rate-kivabe-bujhbo', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/faq/success-rate-kom-hole-ki-korbo', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/faq/cod-order-otp-kokhon', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/faq/woocommerce-customer-blacklist', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/faq/duplicate-cod-order-block', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/faq/customer-delivery-history-check', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/faq/customer-fraud-score-ki', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/faq/cod-return-loss-hisab', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/ki-vabe-fake-order-atkabo', 'priority' => '0.95', 'changefreq' => 'weekly'],
            ['path' => '/fake-customer-check', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/en/fake-customer-check', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/bd-courier-ratio-checker', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/fake-order-check', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/courier-checker', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/fake-order-protection', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/return-loss-calculator', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/courier-charge-calculator', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/ads-roas-calculator', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/courier-auto-entry', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/fraudbd-alternative', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/pathao-fraud-check', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/steadfast-fraud-check', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/redx-fraud-check', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/blog', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['path' => '/en', 'priority' => '0.85', 'changefreq' => 'weekly'],
            ['path' => '/en/bd-fraud-checker', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/en/ki-vabe-fake-order-atkabo', 'priority' => '0.95', 'changefreq' => 'weekly'],
            ['path' => '/en/fake-order-protection', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/en/return-loss-calculator', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/en/ads-roas-calculator', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/en/courier-charge-calculator', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/en/fraudbd-alternative', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/en/courier-auto-entry', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/woocommerce-bangladesh', 'priority' => '0.95', 'changefreq' => 'weekly'],
            ['path' => '/steadfast-integration', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/pathao-courier-guide', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/redx-courier-guide', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/woocommerce-mobile-app', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/customer-verification', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/cod-return-reduction', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/woocommerce-notifications', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/facebook-ads-for-woocommerce', 'priority' => '0.85', 'changefreq' => 'monthly'],
            ['path' => '/facebook-page-cod-management', 'priority' => '0.95', 'changefreq' => 'weekly'],
            ['path' => '/about', 'priority' => '0.95', 'changefreq' => 'monthly'],
            ['path' => '/en/woocommerce-bangladesh', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/en/steadfast-integration', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/en/pathao-courier-guide', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/en/redx-courier-guide', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/en/woocommerce-mobile-app', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/en/customer-verification', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/en/cod-return-reduction', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/en/woocommerce-notifications', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/en/facebook-ads-for-woocommerce', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['path' => '/en/facebook-page-cod-management', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['path' => '/en/about', 'priority' => '0.9', 'changefreq' => 'monthly'],
            ['path' => '/en/blog', 'priority' => '0.75', 'changefreq' => 'weekly'],
            ['path' => '/wooeasylife/app/privacy-policy', 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['path' => '/wooeasylife/app/terms-of-service', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ],
    ],
];
