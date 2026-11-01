<?php

$software = [
    'name' => 'EasyLife Courier Check',
    'operatingSystem' => 'WordPress',
    'applicationCategory' => 'BusinessApplication',
];

return [
    'courier_check_plugin' => [
        'title' => 'ফ্রি Courier Ratio Checker Plugin — WooCommerce | WooEasyLife',
        'description' => 'WooCommerce order-এর ভেতরেই কাস্টমারের Pathao, Steadfast, RedX, Paperfly, Carrybee courier success ratio দেখুন। ফ্রি WordPress plugin, API token লাগে না।',
        'canonical_path' => '/courier-check-plugin',
        'robots' => 'index,follow',
        'single_h1' => true,
        'faq_heading' => 'প্রশ্নোত্তর (FAQ)',
        'prerender_h1' => 'WooCommerce-এর জন্য ফ্রি Courier Ratio ও Fraud Checker Plugin',
        'prerender_lead' => 'EasyLife Courier Check একটি ফ্রি WordPress plugin। এটা WooCommerce order-এর পাশেই কাস্টমারের আগের courier history দেখায়: মোট পার্সেল, ডেলিভার্ড, রিটার্ন আর success ratio। Pathao, Steadfast, RedX, Paperfly ও Carrybee-এর তথ্য থাকে। কোনো API token বা অ্যাকাউন্ট লাগে না। প্রতিদিন নির্দিষ্ট সংখ্যক check ফ্রি।',
        'plugin_download_label' => 'শীঘ্রই WordPress.org-এ আসছে',
        'plugin_checker_href' => '/bd-fraud-checker',
        'plugin_checker_label' => 'ওয়েবে ফ্রি ফ্রড চেকার',
        'plugin_pricing_href' => '/pricing',
        'plugin_pricing_label' => 'প্ল্যান ও ফ্রি ট্রায়াল দেখুন',
        'plugin_footer' => 'তৈরি করেছেন মুহিব্বুল্লাহ আনসারী, Founder, WPSaleHub',
        'plugin_about_href' => '/about',
        'software_application' => $software,
        'hreflang_paths' => [
            'bn-BD' => '/courier-check-plugin',
            'en' => '/en/courier-check-plugin',
            'x-default' => '/courier-check-plugin',
        ],
        'breadcrumbs' => [
            ['name' => 'হোম', 'path' => '/'],
            ['name' => 'WordPress প্লাগইন', 'path' => '/courier-check-plugin'],
        ],
        'content_sections' => [
            [
                'heading' => 'কেন অর্ডার কনফার্মের আগে courier history দেখবেন',
                'paragraphs' => [
                    'COD পার্সেল ফেরত এলে ডেলিভারি চার্জ, রিটার্ন চার্জ আর সময়, তিনটাই নষ্ট হয়। কাস্টমারের আগের পার্সেলগুলো কতবার গ্রহণ করা হয়েছে, সেটা দেখে সিদ্ধান্ত নেওয়া সহজ হয়।',
                ],
                'note' => 'কম success ratio মানেই কাস্টমার ভুয়া নয়। এটা একটা সংকেত মাত্র। কল, OTP বা অগ্রিম দিয়ে নিশ্চিত হোন।',
            ],
            [
                'heading' => 'কীভাবে কাজ করে (৩ ধাপ)',
                'layout' => 'steps',
                'list' => [
                    'Plugin ইনস্টল করে Courier Check → Settings-এ lookup চালু করুন।',
                    'Courier Check → Orders-এ যেকোনো order-এ "Check history" চাপুন।',
                    'Total, Delivered, Cancel আর Rate দেখে কনফার্ম করুন, কল করুন বা অগ্রিম চান।',
                ],
            ],
            [
                'heading' => 'Plugin-এ যা আছে',
                'layout' => 'features',
                'list' => [
                    'প্রতিটি order-এ Delivery history কলাম ও order পেজে বক্স',
                    'যেকোনো নম্বর আলাদাভাবে চেক (Fraud Checker)',
                    'আপনার নিজের স্টোরে এই নম্বরের আগের order: কয়টা ডেলিভার্ড, কয়টা ক্যানসেল',
                    '২৪ ঘণ্টার মধ্যে একই নম্বরের ডুপ্লিকেট order-এর সতর্কতা',
                    'প্রোডাক্টের ছবি, ডেলিভারি চার্জ, order কোথা থেকে এসেছে (source)',
                    'এক ক্লিকে কল, WhatsApp বা নম্বর কপি',
                    'চাইলে নতুন order-এ অটো চেক (চেকআউট ধীর হয় না)',
                    'HPOS সাপোর্ট, প্রাইভেসি-ফার্স্ট: আপনি চালু না করা পর্যন্ত কিছুই পাঠানো হয় না',
                ],
            ],
            [
                'heading' => 'ফ্রি plugin বনাম WooEasyLife',
                'layout' => 'comparison',
                'list' => [
                    'Order-এ courier history | ✓ | ✓',
                    'যেকোনো নম্বর চেক | ✓ | ✓',
                    'একসাথে অনেক order-এর bulk history check | — | ✓',
                    'Courier auto entry (Pathao, Steadfast, RedX, Paperfly) | — | ✓ /courier-auto-entry',
                    'চেকআউটে phone OTP, duplicate block, দৈনিক order limit | — | ✓ /fake-order-protection',
                    'ফোন, ইমেইল, IP বা ডিভাইস blacklist | — | ✓',
                    'মোবাইল অ্যাপ | — | ✓',
                ],
                'rows' => [
                    ['feature' => 'Order-এ courier history', 'free' => '✓', 'paid' => '✓'],
                    ['feature' => 'যেকোনো নম্বর চেক', 'free' => '✓', 'paid' => '✓'],
                    ['feature' => 'একসাথে অনেক order-এর bulk history check', 'free' => '—', 'paid' => '✓'],
                    ['feature' => 'Courier auto entry (Pathao, Steadfast, RedX, Paperfly)', 'free' => '—', 'paid' => '✓', 'href' => '/courier-auto-entry'],
                    ['feature' => 'চেকআউটে phone OTP, duplicate block, দৈনিক order limit', 'free' => '—', 'paid' => '✓', 'href' => '/fake-order-protection'],
                    ['feature' => 'ফোন, ইমেইল, IP বা ডিভাইস blacklist', 'free' => '—', 'paid' => '✓'],
                    ['feature' => 'মোবাইল অ্যাপ', 'free' => '—', 'paid' => '✓'],
                ],
            ],
        ],
        'faqs' => [
            [
                'q' => 'API token বা অ্যাকাউন্ট লাগে?',
                'a' => 'না। Settings-এ lookup চালু করলেই চলবে।',
            ],
            [
                'q' => 'কোন কোন courier?',
                'a' => 'Pathao, Steadfast, RedX, Paperfly, Carrybee। কিছু নম্বরে তথ্য না-ও থাকতে পারে।',
            ],
            [
                'q' => 'Courier success ratio কী?',
                'a' => 'মোট পার্সেলের মধ্যে কত শতাংশ ডেলিভার্ড হয়েছে। যেমন ১০টার মধ্যে ৮টা = ৮০%। /faq/courier-success-rate-kivabe-bujhbo',
            ],
            [
                'q' => 'দৈনিক limit শেষ হলে?',
                'a' => 'পরদিন আবার check করা যাবে। আগের ফলাফল order-এ থেকে যায়।',
            ],
            [
                'q' => 'কাস্টমারের কী তথ্য পাঠানো হয়?',
                'a' => 'শুধু billing phone নম্বর আর আপনার সাইটের URL। /wooeasylife/courier-check/privacy-policy',
            ],
            [
                'q' => 'ফ্রি plugin আর ওয়েবের ফ্রি ফ্রড চেকার কি একই?',
                'a' => 'একই তথ্যসূত্র। Plugin-এ ফলাফল সরাসরি WooCommerce order-এ আসে, নম্বর কপি-পেস্ট করতে হয় না।',
            ],
        ],
    ],

    'en_courier_check_plugin' => [
        'title' => 'Free Courier Ratio Checker Plugin for WooCommerce | WooEasyLife',
        'description' => 'See a customer\'s Pathao, Steadfast, RedX, Paperfly and Carrybee courier success ratio inside WooCommerce orders. Free WordPress plugin, no API token needed.',
        'canonical_path' => '/en/courier-check-plugin',
        'html_lang' => 'en',
        'robots' => 'index,follow',
        'single_h1' => true,
        'faq_heading' => 'FAQ',
        'prerender_h1' => 'Free Courier Ratio & COD Fraud Checker Plugin for WooCommerce',
        'prerender_lead' => 'EasyLife Courier Check is a free WordPress plugin that shows a customer\'s previous courier history next to the WooCommerce order: total parcels, delivered, returned and success ratio. It covers Pathao, Steadfast, RedX, Paperfly and Carrybee. No API token or account is needed, and a number of checks every day are free.',
        'plugin_download_label' => 'Coming soon on WordPress.org',
        'plugin_checker_href' => '/en/bd-fraud-checker',
        'plugin_checker_label' => 'Free fraud checker on the web',
        'plugin_pricing_href' => '/pricing',
        'plugin_pricing_label' => 'See plans and free trial',
        'plugin_footer' => 'Built by Muhibbullah Ansary, Founder, WPSaleHub',
        'plugin_about_href' => '/en/about',
        'software_application' => $software,
        'hreflang_paths' => [
            'bn-BD' => '/courier-check-plugin',
            'en' => '/en/courier-check-plugin',
            'x-default' => '/courier-check-plugin',
        ],
        'breadcrumbs' => [
            ['name' => 'Home', 'path' => '/en'],
            ['name' => 'WordPress Plugin', 'path' => '/en/courier-check-plugin'],
        ],
        'content_sections' => [
            [
                'heading' => 'Why check courier history before you confirm an order',
                'paragraphs' => [
                    'When a COD parcel comes back, you lose the delivery charge, the return charge and your time. Seeing how often the customer accepted earlier parcels makes the decision easier.',
                ],
                'note' => 'A low success ratio does not mean the customer is fake. It is one signal. Confirm with a call, OTP or an advance payment.',
            ],
            [
                'heading' => 'How it works (3 steps)',
                'layout' => 'steps',
                'list' => [
                    'Install the plugin and turn on lookups in Courier Check → Settings.',
                    'Open Courier Check → Orders and click "Check history" on any order.',
                    'Look at Total, Delivered, Cancel and Rate, then confirm, call, or ask for an advance.',
                ],
            ],
            [
                'heading' => 'What\'s included',
                'layout' => 'features',
                'list' => [
                    'A Delivery history column on every order and a box on the order screen',
                    'Check any phone number on its own (Fraud Checker)',
                    'Earlier orders from the same number in your own store: how many delivered, how many cancelled',
                    'A warning for duplicate orders from the same number within 24 hours',
                    'Product images, delivery charge and order source at a glance',
                    'One-click call, WhatsApp or copy number',
                    'Optional automatic check for new orders (checkout is not slowed down)',
                    'HPOS support, privacy-first: nothing is sent until you turn lookups on',
                ],
            ],
            [
                'heading' => 'Free plugin vs WooEasyLife',
                'layout' => 'comparison',
                'list' => [
                    'Courier history on orders | ✓ | ✓',
                    'Check any phone number | ✓ | ✓',
                    'Bulk history check for many orders at once | — | ✓',
                    'Courier auto entry (Pathao, Steadfast, RedX, Paperfly) | — | ✓ /en/courier-auto-entry',
                    'Checkout phone OTP, duplicate block, daily order limit | — | ✓ /en/fake-order-protection',
                    'Blacklist by phone, email, IP or device | — | ✓',
                    'Mobile app | — | ✓',
                ],
                'rows' => [
                    ['feature' => 'Courier history on orders', 'free' => '✓', 'paid' => '✓'],
                    ['feature' => 'Check any phone number', 'free' => '✓', 'paid' => '✓'],
                    ['feature' => 'Bulk history check for many orders at once', 'free' => '—', 'paid' => '✓'],
                    ['feature' => 'Courier auto entry (Pathao, Steadfast, RedX, Paperfly)', 'free' => '—', 'paid' => '✓', 'href' => '/en/courier-auto-entry'],
                    ['feature' => 'Checkout phone OTP, duplicate block, daily order limit', 'free' => '—', 'paid' => '✓', 'href' => '/en/fake-order-protection'],
                    ['feature' => 'Blacklist by phone, email, IP or device', 'free' => '—', 'paid' => '✓'],
                    ['feature' => 'Mobile app', 'free' => '—', 'paid' => '✓'],
                ],
            ],
        ],
        'faqs' => [
            [
                'q' => 'Do I need an API token or an account?',
                'a' => 'No. Just turn on lookups in Settings.',
            ],
            [
                'q' => 'Which couriers are covered?',
                'a' => 'Pathao, Steadfast, RedX, Paperfly and Carrybee. Some numbers may have no data.',
            ],
            [
                'q' => 'What is courier success ratio?',
                'a' => 'The share of a customer\'s parcels that were delivered. For example, 8 out of 10 = 80%.',
            ],
            [
                'q' => 'What happens when the daily limit is reached?',
                'a' => 'You can check again the next day. Earlier results stay on the order.',
            ],
            [
                'q' => 'What customer data is sent?',
                'a' => 'Only the billing phone number and your site URL. /wooeasylife/courier-check/privacy-policy',
            ],
            [
                'q' => 'Is the free plugin the same as the free web fraud checker?',
                'a' => 'Same data source. The plugin shows the result inside the WooCommerce order, so you don\'t have to copy and paste numbers.',
            ],
        ],
    ],
];
