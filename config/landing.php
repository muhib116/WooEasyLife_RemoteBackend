<?php

return [
    'whatsapp_phone' => env('LANDING_WHATSAPP_PHONE'),

    'whatsapp_default_message' => env(
        'LANDING_WHATSAPP_DEFAULT_MESSAGE',
        'সালাম, আমি WooEasyLife সাবস্ক্রিপশন নিতে চাই।',
    ),

    'helpline_phone' => env('LANDING_HELPLINE_PHONE', env('LANDING_WHATSAPP_PHONE')),

    'admin_email' => env('LANDING_ADMIN_EMAIL', env('MAIL_FROM_ADDRESS')),

    'bkash_number' => env('LANDING_BKASH_NUMBER', env('SUBSCRIPTION_PAYMENT_BKASH')),

    'rocket_number' => env('LANDING_ROCKET_NUMBER', env('SUBSCRIPTION_PAYMENT_ROCKET')),

    'nagad_number' => env('LANDING_NAGAD_NUMBER', env('SUBSCRIPTION_PAYMENT_NAGAD')),

    'conversion_notifications' => [
        'email' => (bool) env('LANDING_CONVERT_NOTIFY_EMAIL', true),
        'sms' => (bool) env('LANDING_CONVERT_NOTIFY_SMS', true),
    ],

    'app_download_url' => env('WOOEASYLIFE_ANDROID_DOWNLOAD_URL'),

    'play_store_url' => env('WOOEASYLIFE_PLAY_STORE_URL'),

    'plugin_download_url' => env('WOOEASYLIFE_PLUGIN_DOWNLOAD_URL'),

    'meta_pixel_id' => env('LANDING_META_PIXEL_ID'),

    /*
    | Optional raw HTML injected into public pages (app.blade.php).
    | Prefer Marketing Settings in admin; env is only a deploy-time fallback.
    */
    'header_scripts' => env('LANDING_HEADER_SCRIPTS'),

    'footer_scripts' => env('LANDING_FOOTER_SCRIPTS'),

    'openai_api_key' => env('OPENAI_API_KEY'),

    'openai_blog_model' => env('OPENAI_BLOG_MODEL', 'gpt-4o-mini'),

    /*
    | Mid-tier model for research, outline, competitor analysis, and step review.
    | Leave empty to reuse openai_blog_model.
    */
    'openai_blog_planning_model' => env('OPENAI_BLOG_PLANNING_MODEL', 'gpt-4.1-mini'),

    /*
    | Dedicated model for long-form article drafts (generateDraft / Fix SEO expand).
    | Leave empty to reuse planning model. Prefer gpt-4.1 / gpt-4o / gpt-5 for quality.
    */
    'openai_blog_writing_model' => env('OPENAI_BLOG_WRITING_MODEL', 'gpt-4.1'),

    'openai_image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),

    'subscription_wizard' => [
        'title' => 'সাবস্ক্রিপশন কিনুন',
        'support_hint' => 'কোনো ধাপ বুঝতে সমস্যা? WhatsApp-এ সরাসরি সাহায্য নিন।',
        'steps' => [
            'plan' => 'প্ল্যান',
            'contact' => 'আপনার তথ্য',
            'payment' => 'পেমেন্ট করুন',
            'confirm' => 'তথ্য জমা দিন',
            'done' => 'সম্পন্ন',
        ],
        'free_trial_hint' => 'ফ্রি ট্রায়াল শুরু করতে ফর্ম পূরণ করুন — কোনো পেমেন্ট লাগবে না।',
        'success_title' => 'আপনার অনুরোধ জমা হয়েছে!',
        'success_message' => 'আমাদের টিম ২৪ ঘণ্টার মধ্যে যাচাই করে আপনার প্ল্যান সক্রিয় করবে। WhatsApp-এ আপডেট পেতে পারেন।',
    ],

    'location' => 'ঢাকা, বাংলাদেশ',

    'announcement' => [
        'enabled' => env('LANDING_ANNOUNCEMENT_ENABLED', true),
        'messages' => [
            'ফেক অর্ডার আটকান — মাসে হাজার হাজার টাকার রিটার্ন লস কমান',
            'কুরিয়ার এন্ট্রি অটো — দিনে ৩+ ঘণ্টা সময় বাঁচান',
            'হারিয়ে যাওয়া অর্ডার ফেরান — এক ক্লিকে বিক্রি ফিরিয়ে আনুন',
        ],
    ],

    'footer_tagline' => 'বাংলাদেশের অনলাইন ব্যবসার জন্য — ফেক অর্ডার আটকান, কুরিয়ার সহজ করুন, লাভ বাড়ান।',

    'footer_tagline_en' => "Built for Bangladesh online sellers — stop fake orders, simplify courier, grow profit.",

    'trust_badges' => [
        ['icon' => 'check', 'label' => '১৪ দিন ফ্রি ট্রায়াল'],
        ['icon' => 'clock', 'label' => 'হোয়াটসঅ্যাপ সাপোর্ট'],
        // Payment label is filled dynamically from Landing Settings.
        ['icon' => 'lock', 'label' => 'payment_methods'],
    ],

    'feature_highlight_order' => [
        'ai_text_order_create',
        'ai_image_to_order_create',
        'common_dashboard',
        'multistore_order_notifications',
        'fraud_customer_checker',
        'customer_call_identifier',
        'three_courier_partner_integration',
        'courier_entry_automation',
        'parcel_note_history',
        'ai_driven_customer_scoring',
        'ai_incomplete_address_autocomplete',
        'cross_store_order_detection',
        'centralized_notifications',
        'duplicate_order_validation',
        'customer_sms_for_order',
        'courier_auto_status_sync',
        'admin_employee_manage',
        'call_history_with_duration',
    ],

    'hero' => [
        'badge' => 'শুধুমাত্র WooCommerce স্টোরের জন্য · BD Fraud Checker',
        'headline' => 'ফেক অর্ডারে কি প্রতি মাসে হাজার হাজার টাকা লস হচ্ছে?',
        'headline_accent' => 'ফ্রড চেকার দিয়ে অর্ডার কনফার্মের আগে কাস্টমারের কুরিয়ার হিস্টোরি যাচাই করুন।',
        'subheadline' => 'অর্ডার কনফার্ম হলেই কুরিয়ারে অটো এন্ট্রি করুন, আর কার্টে রেখে চলে যাওয়া কাস্টমারদের ফলো-আপ করে হারিয়ে যাওয়া অর্ডার ফিরিয়ে আনুন। এতে প্রতি মাসে হাজার হাজার টাকা বাঁচাতে পারবেন।',
    ],

    'hero_trust_badges' => [
        '১৪ দিন ফ্রি ট্রায়াল',
        'payment_methods',
        'হোয়াটসঅ্যাপ সাপোর্ট',
    ],

    'hero_bullets' => [
        'দিনে মাত্র ১০টি ফেক অর্ডার আটকাতে পারলেই মাসে প্রায় ৳৪৫,০০০+ রিটার্ন লস কমানো সম্ভব',
        'কুরিয়ার প্যানেলে বারবার যেতে হবে না—অর্ডার কনফার্ম হলেই কুরিয়ারে অটো এন্ট্রি, দিনে ৩+ ঘণ্টা সময় বাঁচান',
        'অর্ডার করতে এসে মাঝপথে চলে যাওয়া কাস্টমারদের আবার অর্ডারে ফিরিয়ে আনুন, একই Ad Cost-এ আরও বেশি বিক্রি করুন।',
    ],

    'roi_scenarios' => [
        [
            'icon' => '💸',
            'title' => 'রিটার্ন লস কমান',
            'calculation' => 'প্রতি রিটার্ন ≈ ৳১৫০–৩০০ · দিনে ১০টা ফেক অর্ডার = মাসে ৳৪৫,০০০+',
            'benefit' => 'ফেক অর্ডার অর্ধেক কমাতে পারলেই মাসে প্রায় ৳২২,৫০০ পর্যন্ত রিটার্ন লস বাঁচানো সম্ভব।',
            'accent' => 'rose',
        ],
        [
            'icon' => '⏱️',
            'title' => 'প্রতিদিন প্রায় ২ ঘণ্টা সময় বাঁচান',
            'calculation' => 'প্রতি অর্ডারে মাত্র ২ মিনিট বাঁচালেই ৫০টি অর্ডারে দিনে প্রায় ১ ঘণ্টা ৪০ মিনিট সাশ্রয়।',
            'benefit' => 'সেই সময় ব্যবসা বাড়ানোর কাজে ব্যবহার করুন।',
            'accent' => 'amber',
        ],
        [
            'icon' => '📱',
            'title' => 'কার্টে রেখে চলে যাওয়া কাস্টমারদের আবার ফিরিয়ে আনুন',
            'calculation' => 'দিনে মাত্র ৫টি অর্ডার ফিরে পেলেই ≈ মাসে ৳৭৫,০০০+ অতিরিক্ত বিক্রি।',
            'benefit' => 'নতুন Ad Cost নয়—আগের আগ্রহী কাস্টমারদের থেকেই আরও বিক্রি করুন।',
            'accent' => 'sky',
        ],
        [
            'icon' => '📦',
            'title' => 'প্যাকেজিং এখন সুপার সিম্পল',
            'calculation' => 'আগে অর্ডার আইডি লিখে মিলাতে সময় লাগত, ভুল হওয়ারও ঝুঁকি ছিল।',
            'benefit' => 'এখন প্রিন্টেড স্টিকার লাগান—দ্রুত প্যাকিং করুন, ভুল কমান।',
            'accent' => 'violet',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Interactive ROI calculator
    |--------------------------------------------------------------------------
    | Powers the "নিজেই হিসাব করুন" calculator. Users adjust sliders and see a
    | personalized monthly saving estimate. Tune the assumptions below.
    */
    'roi_calculator' => [
        'badge' => 'নিজেই হিসাব করুন',
        'headline' => 'রিটার্ন লস কমিয়ে মাসে কত টাকা বাঁচাতে পারবেন?',
        'subtitle' => 'দৈনিক অর্ডার আর রিটার্ন রেট দিন — নিচের সংখ্যা আপনার হিসাবে বদলাবে।',
        'note' => '* আনুমানিক হিসাব; আপনার প্রকৃত সংখ্যা ভিন্ন হতে পারে। মাসে ৩০ দিন ধরা হয়েছে।',
        'subscription_note' => 'সাবস্ক্রিপশন শুরু মাত্র ৳৯৯৯/মাস — উপরের সাশ্রয়ের তুলনায় খুবই কম।',
        'days_per_month' => 30,
        // Estimated share of returns WooEasyLife's fraud check + automation prevents.
        'reduction_percent' => 40,
        'ui' => [
            'current_loss' => 'বর্তমান মাসিক রিটার্ন লস',
            'returns_line' => 'মাসে ~{returns}টি রিটার্ন × {cost}',
            'savings' => 'WooEasyLife দিয়ে সম্ভাব্য মাসিক সাশ্রয়',
            'rate_line' => 'রিটার্ন রেট {from}% → ~{to}% ({avoided}টি রিটার্ন আটকে)',
            'more_savings' => 'আরও যেসব কাজে আপনার সময় ও খরচ কমবে',
        ],
        'inputs' => [
            'daily_orders' => [
                'label' => 'দৈনিক অর্ডার সংখ্যা',
                'default' => 50,
                'min' => 5,
                'max' => 500,
                'step' => 5,
                'suffix' => 'টি',
            ],
            'return_rate' => [
                'label' => 'বর্তমান রিটার্ন / ক্যানসেল রেট',
                'default' => 25,
                'min' => 5,
                'max' => 70,
                'step' => 1,
                'suffix' => '%',
            ],
            'cost_per_return' => [
                'label' => 'প্রতি রিটার্নে গড় খরচ',
                'default' => 120,
                'min' => 50,
                'max' => 400,
                'step' => 10,
                'prefix' => '৳',
            ],
        ],
    ],

    'roi_calculator_en' => [
        'badge' => 'Calculate it yourself',
        'headline' => 'How much can you save monthly by cutting return loss?',
        'subtitle' => 'Enter daily orders and return rate — the numbers below update with your inputs.',
        'note' => '* Educational estimate; your real numbers may differ. Assumes 30 days per month.',
        'subscription_note' => 'Subscriptions start from about ৳999/month — often a fraction of the savings above.',
        'days_per_month' => 30,
        'reduction_percent' => 40,
        'ui' => [
            'current_loss' => 'Current monthly return loss',
            'returns_line' => '~{returns} returns/month × {cost}',
            'savings' => 'Estimated monthly savings with WooEasyLife',
            'rate_line' => 'Return rate {from}% → ~{to}% ({avoided} returns blocked)',
            'more_savings' => 'Other ways you save time and money',
        ],
        'inputs' => [
            'daily_orders' => [
                'label' => 'Daily order count',
                'default' => 50,
                'min' => 5,
                'max' => 500,
                'step' => 5,
                'suffix' => '',
            ],
            'return_rate' => [
                'label' => 'Current return / cancel rate',
                'default' => 25,
                'min' => 5,
                'max' => 70,
                'step' => 1,
                'suffix' => '%',
            ],
            'cost_per_return' => [
                'label' => 'Average cost per return',
                'default' => 120,
                'min' => 50,
                'max' => 400,
                'step' => 10,
                'prefix' => '৳',
            ],
        ],
    ],

    'roi_scenarios_en' => [
        [
            'icon' => '💸',
            'title' => 'Cut return loss',
            'calculation' => 'About ৳150–300 per return · 10 fake orders/day ≈ ৳45,000+/month.',
            'benefit' => 'Cutting fake orders in half can save roughly ৳22,500/month in return loss.',
            'accent' => 'rose',
        ],
        [
            'icon' => '⏱️',
            'title' => 'Save ~2 hours a day',
            'calculation' => 'Saving just 2 minutes per order across 50 orders ≈ 1 hour 40 minutes/day.',
            'benefit' => 'Use that time to grow the business instead of rework.',
            'accent' => 'amber',
        ],
        [
            'icon' => '📱',
            'title' => 'Recover cart abandoners',
            'calculation' => 'Recovering just 5 orders/day ≈ ৳75,000+ extra monthly sales.',
            'benefit' => 'No new ad cost — convert customers who already showed interest.',
            'accent' => 'sky',
        ],
        [
            'icon' => '📦',
            'title' => 'Simpler packing',
            'calculation' => 'Matching order IDs by hand used to waste time and cause mistakes.',
            'benefit' => 'Printed stickers speed packing and reduce errors.',
            'accent' => 'violet',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Courier charge estimator (public SEO tool)
    |--------------------------------------------------------------------------
    | Approximate BD COD merchant rates for Pathao / Steadfast / RedX.
    | Not live API quotes — keep the disclaimer visible on the page.
    */
    'courier_charge_calculator' => [
        'badge' => 'কুরিয়ার চার্জ ক্যালকুলেটর',
        'headline' => 'Pathao · Steadfast · RedX — ডেলিভারি চার্জ আনুমানিক হিসাব',
        'subtitle' => 'জোন ও ওজন দিন — তিন কুরিয়ারের আনুমানিক চার্জ একসাথে দেখুন। COD ফিসহ হিসাব।',
        'note' => '* আনুমানিক রেট টেবিল (উদাহরণ)। Steadfast পাবলিক প্রাইসিং থেকে সিঙ্ক হতে পারে; Pathao/RedX আনুমানিক হতে পারে। অফিসিয়াল চার্জ কুরিয়ার প্যানেল/কন্ট্রাক্টে যাচাই করুন। COD ফি উদাহরণ হিসেবে যোগ করা হয়েছে।',
        'subscription_note' => 'কনফার্ম হলেই Pathao/Steadfast/RedX অটো এন্ট্রি — প্যানেলে হাতে চার্জ হিসাব কমে। ফেক অর্ডার আটকাতে আগে ফ্রড চেক করুন।',
        'zones' => [
            'dhaka' => 'ঢাকার ভিতর',
            'suburb' => 'ঢাকার আশেপাশে / সাবআরবান',
            'outside' => 'ঢাকার বাইরে',
        ],
        'inputs' => [
            'weight_kg' => [
                'label' => 'পার্সেল ওজন',
                'default' => 1,
                'min' => 0.5,
                'max' => 10,
                'step' => 0.5,
                'suffix' => ' কেজি',
            ],
            'cod_amount' => [
                'label' => 'COD অ্যামাউন্ট (ঐচ্ছিক)',
                'default' => 1500,
                'min' => 0,
                'max' => 20000,
                'step' => 100,
                'prefix' => '৳',
            ],
        ],
        'couriers' => [
            'pathao' => [
                'label' => 'Pathao',
                'cod_percent' => 1.0,
                'included_kg' => 1,
                'zones' => [
                    'dhaka' => ['base' => 70, 'per_kg_extra' => 15],
                    'suburb' => ['base' => 100, 'per_kg_extra' => 20],
                    'outside' => ['base' => 130, 'per_kg_extra' => 25],
                ],
            ],
            'steadfast' => [
                'label' => 'Steadfast',
                'cod_percent' => 1.0,
                'included_kg' => 1,
                'zones' => [
                    'dhaka' => ['base' => 70, 'per_kg_extra' => 15],
                    'suburb' => ['base' => 100, 'per_kg_extra' => 18],
                    'outside' => ['base' => 120, 'per_kg_extra' => 22],
                ],
            ],
            'redx' => [
                'label' => 'RedX',
                'cod_percent' => 1.0,
                'included_kg' => 1,
                'zones' => [
                    'dhaka' => ['base' => 65, 'per_kg_extra' => 15],
                    'suburb' => ['base' => 95, 'per_kg_extra' => 18],
                    'outside' => ['base' => 125, 'per_kg_extra' => 24],
                ],
            ],
        ],
    ],

    'courier_charge_calculator_en' => [
        'badge' => 'Courier charge calculator',
        'headline' => 'Pathao · Steadfast · RedX — estimate delivery charges',
        'subtitle' => 'Enter zone and weight to compare estimates. Approximate rates show when live sync is off.',
        'subtitle_live' => 'Steadfast rates update daily from the official pricing API. Pathao live sync runs when merchant API is configured.',
        'note' => '* Steadfast: official public pricing (steadfast.com.bd/pricing). Pathao: no public rates without merchant login/API — daily samples when configured. RedX: estimate. Final charges follow your courier panel/contract. COD fee is a separate estimate (~1% example).',
        'subscription_note' => 'On confirm, Pathao/Steadfast/RedX auto-entry reduces manual charge math. Check fraud risk before confirming.',
        'zones' => [
            'dhaka' => 'Inside Dhaka',
            'suburb' => 'Dhaka suburb / nearby',
            'outside' => 'Outside Dhaka',
        ],
        'inputs' => [
            'weight_kg' => [
                'label' => 'Parcel weight',
                'suffix' => ' kg',
            ],
            'cod_amount' => [
                'label' => 'COD amount (optional)',
                'prefix' => '৳',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Facebook Ads ROAS calculator (fake-purchase aware)
    |--------------------------------------------------------------------------
    */
    'ads_roas_calculator' => [
        'badge' => 'Ads ROAS ক্যালকুলেটর',
        'headline' => 'ফেক Purchase বাদ দিয়ে আসল Facebook Ads ROAS কত?',
        'subtitle' => 'অ্যাড স্পেন্ড, Pixel Purchase ও ফেক/ক্যানসেল রেট দিন — রিপোর্টেড vs আসল ROAS দেখুন। COD সেলারদের জন্য শিক্ষামূলক হিসাব।',
        'note' => '* আনুমানিক হিসাব। Pixel-এ যাওয়া Purchase ≠ কনফার্মড/ডেলিভার্ড অর্ডার। WooEasyLife পিক্সেল প্রোটেকশন শুধু কনফার্মড অর্ডার পাঠায়। Attribution ও ডেলিভারি রেট অনুযায়ী ফল ভিন্ন হতে পারে।',
        'subscription_note' => 'পিক্সেল প্রোটেকশন চালু করলে ফেক Purchase Facebook-এ যায় না — অ্যাড অপটিমাইজেশন সঠিক থাকে। ফ্রড চেক ও ফেক অর্ডার প্রোটেকশন একসাথে চালান।',
        'inputs' => [
            'ad_spend' => [
                'label' => 'মাসিক Facebook Ads স্পেন্ড',
                'default' => 50000,
                'min' => 5000,
                'max' => 500000,
                'step' => 1000,
                'prefix' => '৳',
            ],
            'pixel_purchases' => [
                'label' => 'Pixel-এ Purchase ইভেন্ট (মাসিক)',
                'default' => 200,
                'min' => 10,
                'max' => 2000,
                'step' => 5,
                'suffix' => 'টি',
            ],
            'fake_cancel_rate' => [
                'label' => 'ফেক / ক্যানসেল / রিটার্ন রেট',
                'default' => 30,
                'min' => 0,
                'max' => 80,
                'step' => 1,
                'suffix' => '%',
            ],
            'aov' => [
                'label' => 'গড় অর্ডার ভ্যালু (AOV)',
                'default' => 1200,
                'min' => 300,
                'max' => 5000,
                'step' => 50,
                'prefix' => '৳',
            ],
        ],
    ],

    'ads_roas_calculator_en' => [
        'badge' => 'Ads ROAS calculator',
        'headline' => 'What is your real Facebook Ads ROAS after removing fake purchases?',
        'subtitle' => 'Enter ad spend, Pixel purchases, and fake/cancel rate — compare reported vs real ROAS. Educational math for COD sellers.',
        'note' => '* Approximate estimate. Pixel Purchase ≠ confirmed/delivered order. WooEasyLife pixel protection sends only confirmed purchases. Results vary with attribution and delivery rate.',
        'subscription_note' => 'With pixel protection, fake purchases do not reach Facebook — ad optimization stays cleaner. Pair it with fraud checks and fake-order protection.',
        'inputs' => [
            'ad_spend' => [
                'label' => 'Monthly Facebook Ads spend',
                'default' => 50000,
                'min' => 5000,
                'max' => 500000,
                'step' => 1000,
                'prefix' => '৳',
            ],
            'pixel_purchases' => [
                'label' => 'Pixel Purchase events (monthly)',
                'default' => 200,
                'min' => 10,
                'max' => 2000,
                'step' => 5,
                'suffix' => '',
            ],
            'fake_cancel_rate' => [
                'label' => 'Fake / cancel / return rate',
                'default' => 30,
                'min' => 0,
                'max' => 80,
                'step' => 1,
                'suffix' => '%',
            ],
            'aov' => [
                'label' => 'Average order value (AOV)',
                'default' => 1200,
                'min' => 300,
                'max' => 5000,
                'step' => 50,
                'prefix' => '৳',
            ],
        ],
    ],

    'how_it_works' => [
        [
            'step' => '০১',
            'title' => 'ফ্রি ট্রায়াল শুরু করুন',
            'description' => '১৪ দিন বিনামূল্যে ব্যবহার করুন — WhatsApp-এ পুরো সেটআপে সাহায্য পাবেন',
        ],
        [
            'step' => '০২',
            'title' => 'ওয়েবসাইটে যোগ করুন',
            'description' => 'WooCommerce-এর সাথে WooEasyLife কানেক্ট করুন — কয়েক মিনিটেই সব প্রস্তুত।',
        ],
        [
            'step' => '০৩',
            'title' => 'কম লস, কম ঝামেলা',
            'description' => 'Customer Check ও অটো কুরিয়ার এন্ট্রি চালু করে রিটার্ন কমান, সময় বাঁচান।',
        ],
        [
            'step' => '০৪',
            'title' => 'সব ওয়েবসাইট, এক মোবাইল অ্যাপে',
            'description' => 'সব ওয়েবসাইটের অর্ডার ও আপডেট একসাথে দেখুন—বারবার সাইট বদলানোর দরকার নেই।',
        ],
    ],

    'app_showcase' => [
        'headline' => 'পুরো ব্যবসা এখন আপনার পকেটে',
        'subheadline' => 'নতুন অর্ডার এলেই ফোনে নোটিফিকেশন। হারিয়ে যাওয়া অর্ডারও এক জায়গায়। বাইরে থাকলেও একটাও মিস হবে না।',
        'screenshot' => '/images/woo-easy-life/hub.jpg',
        'screenshot_alt' => 'WooEasyLife মোবাইল অ্যাপ — হোম স্ক্রিন',
        'screenshots' => [
            [
                'src' => '/images/woo-easy-life/hub.jpg',
                'alt' => 'WooEasyLife অ্যাপ হোম — Dashboard, Orders, Missing, Fraud',
                'label' => 'হোম',
            ],
            [
                'src' => '/images/woo-easy-life/dashboard.jpg',
                'alt' => 'WooEasyLife ড্যাশবোর্ড — অর্ডার ও COD সারাংশ',
                'label' => 'ড্যাশবোর্ড',
            ],
            [
                'src' => '/images/woo-easy-life/orders.jpg',
                'alt' => 'WooEasyLife অর্ডার লিস্ট — Abandoned order ও কল বাটন',
                'label' => 'অর্ডার',
            ],
            [
                'src' => '/images/woo-easy-life/common-dashboard.jpg',
                'alt' => 'WooEasyLife Common Dashboard — একাধিক স্টোর এক নজরে',
                'label' => 'মাল্টি-স্টোর',
            ],
            [
                'src' => '/images/woo-easy-life/menu-nav.jpg',
                'alt' => 'WooEasyLife মেনু — Missing Orders ও New অর্ডার ব্যাজ',
                'label' => 'মেনু',
            ],
        ],
        'benefits' => [
            'সব ওয়েবসাইটের অর্ডার সংখ্যা এক নজরে',
            'হারিয়ে যাওয়া অর্ডার খুঁজে বিক্রি ফেরান',
            'নতুন অর্ডার এলেই ফোনে অ্যালার্ট',
            'স্টাফ ও একাধিক সাইট — সব এক অ্যাপে',
        ],
        // Social proof for the app (edit to your real numbers).
        'download_count' => '৫,০০০+',
        'rating' => '৪.৮',
        'rating_count' => '৩২০+',
    ],

    'fraud_benefit_cards' => [
        'headline' => 'অর্ডার কনফার্ম করার আগে ফ্রড চেকার দিয়ে Courier History দেখে নিন।',
        'subtitle' => 'একবার পার্সেল চলে গেলে রিটার্নের খরচ আপনার ঘাড়ে। কুরিয়ার রেকর্ড দেখে আগেই সিদ্ধান্ত নিন।',
        'cards' => [
            [
                'icon' => '📊',
                'title' => 'ডেলিভারি ও রিটার্ন হিস্টোরি',
                'description' => 'সব সাপোর্টেড কুরিয়ারে কাস্টমারের ডেলিভারি ও রিটার্ন রেকর্ড এক জায়গায় দেখুন।',
            ],
            [
                'icon' => '✅',
                'title' => 'আগের অর্ডারের রেকর্ড',
                'description' => 'এই নম্বরে আগে কতটি অর্ডার হয়েছে, কতটি সফলভাবে ডেলিভারি হয়েছে এবং কতটি রিটার্ন হয়েছে—সব এক জায়গায় দেখুন।',
            ],
            [
                'icon' => '🛡️',
                'title' => 'ফেক অর্ডারের ঝুঁকি কমান',
                'description' => 'কম ডেলিভারি হার বা সতর্কবার্তা দেখেই ঝুঁকিপূর্ণ অর্ডার শনাক্ত করুন।',
            ],
            [
                'icon' => '📱',
                'title' => 'ফ্রি-তে কাস্টমারের Courier History দেখুন',
                'description' => 'রেজিস্ট্রেশন ছাড়াই নম্বর দিয়ে প্রতিদিন ৫টি ফ্রি চেক করুন।',
                'cta' => '#fraud-check',
                'cta_label' => 'ফ্রি ফ্রড চেক করুন',
            ],
        ],
    ],

    'case_studies' => [
        'badge' => 'সেলারদের অভিজ্ঞতা',
        'headline' => 'রিটার্ন লস কমিয়ে মাসে হাজার হাজার টাকা বাঁচান',
        'subtitle' => 'অনামায়িত বাস্তবধর্মী উদাহরণ — ফ্রড চেক ও ফেক অর্ডার প্রোটেকশন চালু করার পর সেলারদের সাশ্রয়।',
        'items' => [
            [
                'name' => 'ফ্যাশন স্টোর · ঢাকা',
                'role' => 'দিনে ~২৫–৩০ COD অর্ডার',
                'savings' => '৳৩৮,০০০+',
                'period' => 'মাসিক সাশ্রয়',
                'quote' => 'আগে দিনে ৮–১০টি ফেক/রিটার্ন যেত। নম্বর চেক ও OTP চালু করার পর রিটার্ন প্রায় অর্ধেক। শুধু কুরিয়ার চার্জেই মাসে ৳৩৮ হাজারের মতো বাঁচে।',
            ],
            [
                'name' => 'গ্যাজেট শপ · চট্টগ্রাম',
                'role' => 'Facebook অ্যাড + COD',
                'savings' => '৳৫২,০০০+',
                'period' => 'মাসিক সাশ্রয়',
                'quote' => 'অ্যাড থেকে আসা লো-কোয়ালিটি লিড আগে পার্সেল হয়ে যেত। কুরিয়ার হিস্টোরি দেখে কনফার্ম করি — প্যাকেজিং ও রিটার্ন মিলিয়ে মাসে ৳৫০ হাজারের ওপরে সাশ্রয়।',
            ],
            [
                'name' => 'হোম কেয়ার ব্র্যান্ড · সিলেট',
                'role' => 'Pathao + Steadfast',
                'savings' => '৳২৭,৫০০+',
                'period' => 'মাসিক সাশ্রয়',
                'quote' => 'ম্যানুয়াল কুরিয়ার এন্ট্রি ভুল আর ফেক অর্ডার দুটোই কমলো। অটো এন্ট্রি ও ফ্রড চেক একসাথে চালু করে মাসে প্রায় ৳২৮ হাজার লস কমেছে।',
            ],
        ],
    ],

    'feature_showcases' => [
        [
            'id' => 'fraud',
            'badge' => 'ফেক অর্ডার থেকে বাঁচুন',
            'headline' => 'ফেক অর্ডারের জন্য স্মার্ট মাল্টি-লেয়ার প্রোটেকশন',
            'teaser' => 'কুরিয়ার রেকর্ড + নম্বর যাচাই + অটো ব্লক',
            'pain' => 'ফেক অর্ডার একবার পাঠিয়ে দিলে রিটার্ন চার্জ, প্যাকেজিং আর সময় — সব আপনার পকেট থেকে যায়। অনেক ব্যবসায়ী মাসে লাখ টাকারও বেশি লস করেন।',
            'solution' => 'অর্ডার কনফার্মের আগে Courier History চেক করুন। Checkout-এ ফোন OTP, BD এরিয়া পিকার, ডুপ্লিকেট অর্ডার ব্লক, দৈনিক লিমিট ও (প্রয়োজনে) চেকআউট থ্রোটল চালু করুন — সমস্যাযুক্ত নম্বর ব্লক রাখুন।',
            'benefit' => 'ঝুঁকিপূর্ণ অর্ডার আগেই আটকে যায় — রিটার্ন কমে, সফল ডেলিভারি বাড়ে, মাসে হাজার হাজার টাকা বাঁচে।',
            'profit' => [
                'monthly' => '৳৪৫,০০০+',
                'basis' => 'দিনে ১০টি ফেক অর্ডার এড়াতে পারলে × গড়ে প্রতি রিটার্নে ৳১৫০ খরচ × ৩০ দিন = ≈ ৳৪৫,০০০+ সাশ্রয়',
                'compare' => 'WooEasyLife শুরু মাত্র ৳৯৯৯/মাস — শুধু এই একটি ফিচার থেকেই সাবস্ক্রিপশনের খরচ কয়েকগুণ উঠে আসতে পারে।',
            ],
            'highlights' => [
                'কুরিয়ার ডেলিভারি হার দেখে সিদ্ধান্ত',
                'চেকআউটে নম্বর OTP যাচাই (প্ল্যাটফর্ম OTP SMS)',
                'BD ডিভিশন→জেলা→উপজেলা→ইউনিয়ন পিকার',
                'একই কার্ট ডুপ্লিকেট ব্লক (ফোন/ইমেইল/ডিভাইস)',
                'ঐচ্ছিক চেকআউট থ্রোটল ও OTP soft-pass',
                'সমস্যাযুক্ত নম্বর/ডিভাইস ব্লক',
            ],
            'read_more' => [
                [
                    'title' => 'ফ্রড চেক কেন দরকার?',
                    'body' => 'বাংলাদেশে ক্যাশ অন ডেলিভারি ব্যবসায় ফেক অর্ডার সবচেয়ে বড় মাথাব্যথা। একবার পার্সেল চলে গেলে রিটার্ন চার্জ, প্যাকেজিং আর সময় — সব লস। WooEasyLife-এ অর্ডার পাঠানোর আগেই কুরিয়ার রেকর্ড দেখে সিদ্ধান্ত নিন।',
                ],
                [
                    'title' => 'কী কী সুরক্ষা পাবেন?',
                    'body' => 'চেকআউটে: ফোন OTP (সার্ভার-সাইড গেট), BD এরিয়া ফিল্ড, নাম-ঠিকানা যাচাই, একই কার্ট ডুপ্লিকেট ব্লক, দৈনিক অর্ডার সীমা, ঐচ্ছিক অ্যাটেম্পট থ্রোটল। সুরক্ষায়: নম্বর/ইমেইল/ডিভাইস ব্লক, শুধু বাংলাদেশি অর্ডার, কাস্টম ব্ল্যাকলিস্ট। প্রতিটি অপশন Security → Checkout থেকে এক ক্লিকে চালু/বন্ধ।',
                ],
                [
                    'title' => 'ফ্রড চেক কীভাবে কাজ করে?',
                    'body' => 'কাস্টমারের নম্বর দিয়ে Pathao, Steadfast, RedX, Paperfly ও Carrybee — পাঁচটি কুরিয়ারের ডেলিভারি রেকর্ড একসাথে দেখুন। ঝুঁকি বেশি হলে কনফার্ম করবেন নাকি বাতিল — সিদ্ধান্ত পুরোপুরি আপনার হাতে।',
                ],
            ],
            'accent' => 'emerald',
            'feature_keys' => ['fraud_customer_checker', 'checkout_otp_validation', 'checkout_bd_area_fields', 'checkout_form_validation', 'duplicate_order_validation', 'duplicate_order_otp_soft_pass', 'checkout_attempt_throttle', 'daily_order_limit', 'ip_block', 'phone_email_block', 'device_block', 'bd_ip_restriction', 'store_api_checkout_protection', 'customer_blacklist'],
        ],
        [
            'id' => 'pixel',
            'badge' => 'ফেসবুক অ্যাড বাঁচান',
            'headline' => 'ফেক অর্ডারে অ্যাড বাজেট নষ্ট হওয়া বন্ধ করুন',
            'teaser' => 'শুধু আসল কনফার্মড অর্ডারকেই Facebook «বিক্রি» ধরে নেবে',
            'pain' => 'অনেক স্টোরে অর্ডার হওয়ার সঙ্গে সঙ্গেই Facebook-এ Purchase পাঠানো হয়। পরে অর্ডারটি ফেক বা ক্যানসেল হলেও Facebook সেটিকে বিক্রি হিসেবে ধরে, ফলে Ad Optimization ভুল ডেটা থেকে শেখে।',
            'solution' => 'WooEasyLife-এ অর্ডার কনফার্ম হওয়ার পরই শুধু Facebook-এ Purchase পাঠানো হয়। ফেক বা ক্যানসেল হওয়া অর্ডার আর Optimization-এ প্রভাব ফেলে না।',
            'benefit' => 'Facebook শুধু আসল ক্রেতাদের থেকেই শেখে। ফলে Ad Budget সঠিক অডিয়েন্সের পেছনে খরচ হয়, Optimization আরও নির্ভুল হয় এবং সময়ের সাথে ভালো মানের বিক্রি পাওয়ার সম্ভাবনা বাড়ে।',
            'profit' => [
                'monthly' => '৳২০,০০০+',
                'basis' => 'ফেক অর্ডার বাদ দিয়ে অ্যাড খরচ ~২০% কমালে — মাসে ৳১,০০,০০০ অ্যাড বাজেটে ≈ ৳২০,০০০ সাশ্রয়',
                'compare' => 'WooEasyLife শুরু মাত্র ৳৯৯৯/মাস — শুধু অ্যাড সাশ্রয়েই সাবস্ক্রিপশনের খরচ কয়েকগুণ উঠে আসতে পারে।',
            ],
            'highlights' => [
                'শুধু কনফার্মড অর্ডারই Facebook-এ পাঠানো হয়',
                'ফেক বা ক্যানসেল অর্ডার Pixel-এ যায় না, তাই Ad Optimization আরও নির্ভুল হয়।',
                'Facebook আসল ক্রেতাদের থেকেই শেখে, ফলে Ad Budget সঠিক অডিয়েন্সের পেছনে খরচ হয়।',
                'ভুল মানুষের কাছে অ্যাড কম যায়',
            ],
            'read_more' => [
                [
                    'title' => 'ফেক অর্ডার অ্যাডের কী ক্ষতি করে?',
                    'body' => 'COD ব্যবসায় অনেক অর্ডারই পরে ফেক বা ক্যানসেল হয়ে যায়। কিন্তু বেশিরভাগ ক্ষেত্রে অর্ডার আসার সঙ্গেই Facebook ধরে নেয় একটি বিক্রি হয়েছে। পরে অর্ডারটি বাতিল হলেও সেই তথ্য আর ঠিক হয় না। ফলে Facebook ভুল ধরনের মানুষকে সম্ভাব্য ক্রেতা মনে করে, একই ধরনের আরও মানুষের কাছে অ্যাড দেখায় এবং অপ্রয়োজনীয় Ad Cost বাড়তে থাকে।',
                ],
                [
                    'title' => 'WooEasyLife কীভাবে সাহায্য করে?',
                    'body' => 'WooEasyLife-এ আপনি অর্ডার কনফার্ম করার পরই শুধু Facebook-কে জানানো হয় যে বিক্রি হয়েছে। চাইলে অন্য কোনো অর্ডার স্ট্যাটাসও নির্বাচন করতে পারবেন। এতে Facebook শুধু আসল ক্রেতাদের থেকেই শেখে। ফলে আপনার Ad Budget আরও কার্যকরভাবে খরচ হয় এবং ভালো মানের অর্ডার পাওয়ার সম্ভাবনা বাড়ে।',
                ],
            ],
            'accent' => 'fuchsia',
            'feature_keys' => ['pixel_protection'],
        ],
        [
            'id' => 'funnels',
            'badge' => 'Funnels · Beta',
            'headline' => 'Facebook Ads-এর জন্য COD ল্যান্ডিং পেজ — কোডিং ছাড়াই',
            'teaser' => 'Funnels (Beta): AI প্রম্পট → Import JSON → /wel/ clean URL',
            'pain' => 'অ্যাড চালাচ্ছেন কিন্তু ল্যান্ডিং পেজ বানাতে Elementor/ডেভেলপার দেরি — কনটেন্ট বদলাতেও ঘণ্টার পর ঘণ্টা লাগে।',
            'solution' => 'WooEasyLife Funnels-এ ফানেল তৈরি করুন, প্রোডাক্ট ও ফোন সেট করুন, ChatGPT/Gemini প্রম্পট দিয়ে কনটেন্ট জেনারেট করে Import JSON করুন — তারপর থিম/লেআউট বেছে পাবলিশ।',
            'benefit' => 'নন-টেকনিক্যাল টিমও দ্রুত সিঙ্গেল-প্রোডাক্ট COD ল্যান্ডিং পেজ লাইভ করতে পারে — অ্যাড ক্রিয়েটিভের সাথে ম্যাচ করা সহজ।',
            'profit' => [
                'label' => 'সময় ও এজেন্সি খরচ',
                'monthly' => 'ঘণ্টা বাঁচে',
                'basis' => 'ম্যানুয়াল পেজ বিল্ড/এজেন্সি রাউন্ডের বদলে একই দিনে ল্যান্ডার আপডেট — উদাহরণভিত্তিক; ফলাফল ব্যবসা অনুযায়ী ভিন্ন',
                'compare' => '১৪ দিন ফ্রি ট্রায়ালে নিজের স্টোরে Funnels টেস্ট করুন।',
                'note' => 'Beta ফিচার — Views/Conversions মেট্রিক্স এখনো প্লেসহোল্ডার। গ্যারান্টিযুক্ত কনভার্সন রেট দাবি করা হয় না।',
            ],
            'highlights' => [
                'Funnels hub → Create → Design Your Funnel',
                'AI prompt + competitor URL/ইমেজ → Import JSON',
                'Inline COD অর্ডার ফর্ম · ফোন/WhatsApp',
                'Clean public URL: /wel/{slug}',
                'Classic / Offer / Voyage / Atlas লেআউট ও থিম',
            ],
            'read_more' => [
                [
                    'title' => 'Funnels কী?',
                    'body' => 'WooEasyLife অ্যাডমিনে Funnels (Beta) হলো সেলস ফানেল হাব। একটি ফানেল তৈরি করলে ল্যান্ডিং পেজ স্টেপ তৈরি হয় — Elementor WEL Landing widget দিয়ে এডিট ও পাবলিশ। পুরনো Elementor ল্যান্ডারও আগের মতোই কাজ করে।',
                ],
                [
                    'title' => 'AI কনটেন্ট কীভাবে?',
                    'body' => 'Config থেকে Reset prompt + fill product → Copy → ChatGPT/Gemini-তে প্রতিযোগী URL বা প্রোডাক্ট ইমেজ দিন → পাওয়া JSON Import করুন। ছবি ম্যানুয়ালি আপলোড করুন, তারপর Publish।',
                ],
            ],
            'scenario' => [
                'title' => 'উদাহরণ ফ্লো (ভিডিওর মতো)',
                'steps' => [
                    'Funnels → Create → নাম ও slug',
                    'ফোন/WhatsApp + চেকআউট প্রোডাক্ট সেট',
                    'AI প্রম্পট কপি → ChatGPT → Import JSON',
                    'ইমেজ/থিম ঠিক করে Publish → /wel/… লিংক অ্যাডে',
                ],
            ],
            'accent' => 'orange',
            'always_show' => true,
            'feature_keys' => [],
        ],
        [
            'id' => 'team',
            'badge' => 'স্টাফ ও কল ট্র্যাকিং',
            'headline' => 'ক্যানসেল হলে জানুন কেন — কে কল করেছিল, কতক্ষণ',
            'teaser' => 'ইনকামিং কলে কাস্টমার শনাক্ত · কলের সময় · কোন স্টাফ — সব এক জায়গায়',
            'pain' => 'অর্ডার ক্যানসেল হলে বোঝা যায় না — স্টাফ ঠিকমতো কল করেছিল কিনা, কতক্ষণ কথা হয়েছিল। কাস্টমার কল করলেও নাম-অর্ডার খুঁজে পেতে দেরি হয়।',
            'solution' => 'কাস্টমার কল করলেই নাম ও অর্ডার হিস্ট্রি দেখা যায়। অ্যাপ দিয়ে কল করলে সময়কাল সেভ হয়। স্টাফ অ্যাসাইন করুন — ক্যানসেল হলে কল + স্টাফ + কারণ একসাথে দেখুন।',
            'benefit' => 'কলের সাথে সাথেই কাস্টমার চিনে দ্রুত রেসপন্স। ক্যানসেলের কারণ বোঝা যায়, স্টাফ ট্রেনিং সহজ হয়।',
            'profit' => [
                'monthly' => '৳১৫,০০০+',
                'basis' => 'সঠিক ফলো-আপে দিনে ৫টি অর্ডার বাঁচান × গড় ৳১০০ লাভ × ৩০ দিন',
                'compare' => 'সাবস্ক্রিপশন শুরু মাত্র ৳৯৯৯/মাস — একটা স্টাফের ভুলে হারানো অর্ডারই এর চেয়ে বেশি।',
            ],
            'highlights' => [
                'কাস্টমার কল করলেই নাম ও অর্ডার হিস্ট্রি দেখা যায়',
                'অ্যাপ থেকে কল — কতক্ষণ কথা হয়েছিল রেকর্ড',
                'অর্ডারে স্টাফ অ্যাসাইন — কে কাজ করছে জানুন',
                'ক্যানসেল অর্ডারে কল + স্টাফ দেখে কারণ বুঝুন',
            ],
            'scenario' => [
                'title' => 'উদাহরণ: ক্যানসেল অর্ডার #৪৮২১',
                'steps' => [
                    'কাস্টমার অর্ডার করল — স্টাফ «রাহিম» অ্যাসাইন',
                    'রাহিম অ্যাপ থেকে ৪ মিনিট ৩২ সেকেন্ড কল করল',
                    'পরে অর্ডার ক্যানসেল — কল রেকর্ড দেখে বোঝা গেল কাস্টমার COD নিতে অনিচ্ছুক',
                    'সিদ্ধান্ত: পরবর্তীতে এই নম্বরে ফোন-কনফার্ম বাধ্যতামূলক',
                ],
            ],
            'read_more' => [
                [
                    'title' => 'কাস্টমার কল শনাক্ত কেন দরকার?',
                    'body' => 'কাস্টমার কল করলেই অ্যাপে নাম, আগের অর্ডার ও হিস্ট্রি দেখা যায়। স্টাফকে আলাদা করে খুঁজতে হয় না—দ্রুত রেসপন্স দিতে পারে, কলের সাথে অর্ডারও লিংক হয়।',
                ],
                [
                    'title' => 'কল রেকর্ড কীভাবে সাহায্য করে?',
                    'body' => 'মোবাইল অ্যাপ দিয়ে কল করলে প্রতিটি কল কত মিনিট স্থায়ী হয়েছিল সেভ হয়। ফলে বোঝা যায় কোন স্টাফ কতক্ষণ কাস্টমারের সাথে কথা বলছে — পারফরম্যান্স মাপা ও ট্রেনিং দেওয়া সহজ।',
                ],
                [
                    'title' => 'স্টাফ অ্যাসাইন কেন দরকার?',
                    'body' => 'প্রতিটি অর্ডারে দায়িত্বশীল স্টাফ সেট করুন। কে কত অর্ডার হ্যান্ডেল করছে, কোন অর্ডারে ক্যানসেল বেশি — মালিক হিসেবে সব দেখতে পারবেন।',
                ],
                [
                    'title' => 'ক্যানসেল হলে ডাটা কীভাবে সাহায্য করে?',
                    'body' => 'শুধু «ক্যানসেল» দেখে থেমে যাওয়ার দিন শেষ। কলের সময়, কোন স্টাফ, কোথা থেকে অর্ডার এসেছে — একসাথে দেখে সিদ্ধান্ত নিন: ফেক কাস্টমার, দুর্বল ফলো-আপ, নাকি প্রোডাক্ট/দামের সমস্যা।',
                ],
            ],
            'accent' => 'violet',
            'feature_keys' => ['customer_call_identifier', 'call_history_with_duration', 'order_source_identifier', 'admin_employee_manage'],
        ],
        [
            'id' => 'courier',
            'badge' => 'কুরিয়ার সহজ',
            'headline' => 'কুরিয়ার প্যানেলে বসে সময় নষ্ট করবেন না',
            'teaser' => 'Pathao, Steadfast, RedX — কনফার্ম হলেই অটো এন্ট্রি',
            'pain' => 'প্রতি অর্ডারে কুরিয়ার ওয়েবসাইটে হাতে এন্ট্রি — দিনে ৩+ ঘণ্টা চলে যায়। ভুল এন্ট্রি হলে ভুল ডেলিভারি।',
            'solution' => 'অর্ডার কনফার্ম হলেই কুরিয়ারে অটো এন্ট্রি, স্ট্যাটাস আপডেট ও কাস্টমারকে এসএমএস। এক জায়গা থেকে সব কুরিয়ার।',
            'benefit' => 'সময় বাঁচে, ভুল কমে — দিনে ৫০+ অর্ডারও স্মুথলি চলে।',
            'profit' => [
                'monthly' => '৳৯,০০০+',
                'basis' => 'দিনে ৩+ ঘণ্টা স্টাফ সময় বাঁচান × ৩০ দিন — প্রায় একজন কর্মীর বেতনের সমান',
                'compare' => 'সাবস্ক্রিপশন শুরু মাত্র ৳৯৯৯/মাস — বাঁচানো স্টাফ খরচের একটা ছোট অংশ।',
            ],
            'highlights' => [
                '৩+ কুরিয়ার এক জায়গায়',
                'কনফার্ম = অটো কুরিয়ার এন্ট্রি',
                'কাস্টমারকে এসএমএস আপডেট',
            ],
            'read_more' => [
                [
                    'title' => 'হাতে এন্ট্রি vs অটো',
                    'body' => 'বারবার কুরিয়ার সাইটে লগইন, কপি-পেস্ট আর ম্যানুয়াল এন্ট্রি—অপ্রয়োজনীয় সময় নষ্ট। WooEasyLife-এ অর্ডার কনফার্ম করলেই কুরিয়ারে অটো এন্ট্রি হয়ে যায়।',
                ],
                [
                    'title' => 'এসএমএস কেন দরকার?',
                    'body' => 'কাস্টমারকে অর্ডার ও ডেলিভারি আপডেট এসএমএসে যায় — «কোথায় আমার পার্সেল?» কল কমে।',
                ],
            ],
            'accent' => 'amber',
            'feature_keys' => ['three_courier_partner_integration', 'courier_entry_automation', 'courier_auto_status_sync', 'customer_sms_for_order', 'parcel_note_history'],
        ],
        [
            'id' => 'missing',
            'badge' => 'হারানো বিক্রি ফেরান',
            'headline' => 'কার্টে রেখে চলে যাওয়া বা অর্ডার সম্পূর্ণ না করা কাস্টমারদের আবার ফিরিয়ে আনুন।',
            'teaser' => 'Missing Order ধরে এক ক্লিকে বিক্রি ফেরান',
            'pain' => 'অনেক কাস্টমার কার্টে প্রোডাক্ট যোগ করে বা Checkout শুরু করেও অর্ডার সম্পন্ন করে না। এসব Missing Order সাধারণত নজরেই আসে না, ফলে সম্ভাব্য অনেক বিক্রি নীরবে হাতছাড়া হয়ে যায়।',
            'solution' => 'সব Missing Order স্বয়ংক্রিয়ভাবে একটি আলাদা লিস্টে সংরক্ষণ হয়—কাস্টমারের নাম, ফোন নম্বর ও নির্বাচিত প্রোডাক্টসহ। সেখান থেকেই কল করুন, Courier History দেখে নিন, তারপর এক ক্লিকে অর্ডার কনফার্ম করুন।',
            'benefit' => 'হারানো বিক্রি আবার ফিরে আসে। নতুন করে Ad Cost করার দরকার নেই—যে কাস্টমারকে আনতে খরচ হয়েছে, তার অর্ডার সম্পন্ন করার আরেকটি সুযোগ পান।',
            'profit' => [
                'label' => 'Missing Order থেকে সম্ভাব্য মাসিক বিক্রি',
                'monthly' => '৳৭৫,০০০+',
                'basis' => 'দিনে ৫টি Missing Order ফিরে এলে × গড় অর্ডার মূল্য ৳৫০০ × ৩০ দিন',
                'highlight' => 'এই কাস্টমারদের আনতে Ad Cost আগেই হয়েছে। শুধু Missing Order-গুলোকে আবার বিক্রিতে রূপান্তর করুন।',
                'compare' => 'WooEasyLife শুরু মাত্র ৳৯৯৯/মাস — একদিনের ফিরে আসা Missing Order-ই অনেক সময় পুরো মাসের সাবস্ক্রিপশন খরচ তুলে দিতে পারে।',
                'note' => 'এটি একটি উদাহরণভিত্তিক হিসাব। প্রকৃত ফলাফল আপনার ব্যবসার ধরন, অর্ডারের সংখ্যা ও কনভার্সন রেট অনুযায়ী ভিন্ন হতে পারে।',
            ],
            'highlights' => [
                'সব Missing Order অটোমেটিক শনাক্ত করুন',
                'এক ক্লিকে কল, Fraud Check ও অর্ডার কনফার্ম করুন',
                'কত টাকা হারিয়েছেন আর কত টাকা Missing Order থেকে ফিরিয়ে এনেছেন—ড্যাশবোর্ডেই দেখুন',
            ],
            'read_more' => [
                [
                    'title' => 'হারানো অর্ডার (Missing Order) কী?',
                    'body' => 'কাস্টমার কার্টে প্রোডাক্ট যোগ করেছে বা Checkout শুরু করেছে, কিন্তু অর্ডার সম্পন্ন করেনি—এগুলোই হারানো অর্ডার (Missing Order)। WooEasyLife এমন সব অর্ডার স্বয়ংক্রিয়ভাবে একটি আলাদা লিস্টে সংরক্ষণ করে, যাতে কোনো সম্ভাব্য বিক্রি চোখ এড়িয়ে না যায়।',
                ],
                [
                    'title' => 'কীভাবে অর্ডার ফিরিয়ে আনবেন?',
                    'body' => 'প্রতিটি Missing Order-এর সাথে কাস্টমারের নাম, ফোন নম্বর ও কার্টের প্রোডাক্ট দেখতে পারবেন। সেখান থেকেই এক ক্লিকে কল করুন, Courier History দেখে নিন, তারপর অর্ডার কনফার্ম করলেই নতুন অর্ডার তৈরি হয়ে যাবে।',
                ],
            ],
            'accent' => 'sky',
            'feature_keys' => ['missing_orders', 'missing_order_one_click_create', 'order_cloning', 'quick_action_tool'],
        ],
        [
            'id' => 'print',
            'badge' => 'প্যাকিং সহজ',
            'headline' => 'পার্সেল স্টিকার — সেকেন্ডে, ভুল ছাড়া',
            'teaser' => 'প্রিন্ট করে লাগান — হাতে লেখার দিন শেষ',
            'pain' => 'হাতে নাম-ঠিকানা লিখতে সময় যায়, ভুল হলে ভুল ডেলিভারি ও রিটার্ন। অর্ডার বেশি হলে প্যাকিংয়েই সব আটকে যায়।',
            'solution' => 'স্টিকার ও ইনভয়েস এক ক্লিকে প্রিন্ট — প্যাকিং লাইন দ্রুত চলে।',
            'benefit' => 'প্যাকিং দ্রুত, ভুল কমে — দিনে আরও অর্ডার পাঠাতে পারবেন।',
            'profit' => [
                'monthly' => '৳৬,০০০+',
                'basis' => 'ভুল ঠিকানার রিটার্ন দিনে ২টি কমান × গড় ৳১০০ × ৩০ দিন + প্যাকিং সময় সাশ্রয়',
                'compare' => 'সাবস্ক্রিপশন শুরু মাত্র ৳৯৯৯/মাস — কমানো রিটার্ন খরচেই পুষিয়ে যায়।',
            ],
            'highlights' => [
                'নাম, ঠিকানা, ফোন প্রিন্ট স্টিকার',
                'এক ক্লিকে ইনভয়েস',
                'প্যাকিং টিমের জন্য স্ট্যান্ডার্ড ফরম্যাট',
            ],
            'read_more' => [
                [
                    'title' => 'প্যাকিং স্পিড',
                    'body' => 'হাতে লেখা লেবেলে ভুল ঠিকানা = রিটার্ন খরচ। প্রিন্টেড স্টিকারে অর্ডারের ডাটা সরাসরি ছাপা হয় — সেকেন্ডে লেবেল, কম ভুল।',
                ],
            ],
            'accent' => 'violet',
            'feature_keys' => ['pos_sticker_print', 'invoice_print', 'inline_shipping_change', 'order_note_management'],
        ],
        [
            'id' => 'ai',
            'badge' => 'মেসেজ থেকে অর্ডার',
            'headline' => 'মেসেজ থেকে অর্ডার — হাতে টাইপের দিন শেষ',
            'teaser' => 'টেক্সট বা ছবি থেকে অর্ডার — প্রায় ৩০ সেকেন্ডে',
            'pain' => 'কাস্টমারের মেসেজ/ছবি দেখে হাতে অর্ডার টাইপ — ৫–১০ মিনিট প্রতি অর্ডার। ভুল ঠিকানা, ভুল প্রোডাক্ট সাধারণ।',
            'solution' => 'মেসেজ বা ছবি দিলেই অর্ডার ফিল্ড অটো পূরণ — দ্রুত ও কম ভুল।',
            'benefit' => '৩০ সেকেন্ডে অর্ডার — দিনে ঘণ্টার পর ঘণ্টা সময় বাঁচে।',
            'profit' => [
                'monthly' => '৳১২,০০০+',
                'basis' => 'অর্ডার এন্ট্রিতে দিনে ২+ ঘণ্টা বাঁচান + ভুল ঠিকানার খরচ কমান × ৩০ দিন',
                'compare' => 'সাবস্ক্রিপশন শুরু মাত্র ৳৯৯৯/মাস — বাঁচানো সময়ের তুলনায় সামান্য।',
            ],
            'highlights' => [
                'মেসেজ পেস্ট — অর্ডার তৈরি',
                'ছবি/স্ক্রিনশট থেকে অর্ডার',
                'অসম্পূর্ণ ঠিকানা সাজেস্ট',
            ],
            'read_more' => [
                [
                    'title' => 'মেসেজ থেকে অর্ডার',
                    'body' => 'কাস্টমার «২টা শার্ট, XL, ঢাকা» লিখল — সিস্টেম সেটা পড়ে অর্ডার ফিল্ড অটো পূরণ করে দেয়। স্ক্রিনশট থেকেও অর্ডার বানানো যায়।',
                ],
                [
                    'title' => 'কাস্টমার স্কোর',
                    'body' => 'কুরিয়ার রেকর্ড মিলিয়ে দেখা যায় কে বিশ্বস্ত, কে ঝুঁকিপূর্ণ — কাকে ফোন-কনফার্ম করবেন সিদ্ধান্ত সহজ।',
                ],
            ],
            'accent' => 'fuchsia',
            'feature_keys' => ['ai_text_order_create', 'ai_image_to_order_create', 'ai_incomplete_address_autocomplete', 'ai_driven_customer_scoring'],
        ],
        [
            'id' => 'websites',
            'badge' => 'একাধিক ওয়েবসাইট',
            'headline' => '২–৩টা ওয়েবসাইট — সব আপনার আঙুলের ডগায়',
            'teaser' => 'সব সাইটের অর্ডার এক ড্যাশবোর্ড ও অ্যাপে',
            'pain' => 'প্রতিটি সাইট আলাদা করে খুলে ঘোরা — সময় নষ্ট, অর্ডার মিস, স্টাফও বিভ্রান্ত।',
            'solution' => 'সব ওয়েবসাইট এক জায়গায় — অর্ডার অ্যালার্ট ফোনে, QR দিয়ে দ্রুত কানেক্ট।',
            'benefit' => 'সাইট বদলাতে দিনে ৩০–৪৫ মিনিট বাঁচে — স্কেল করা সহজ।',
            'profit' => [
                'monthly' => '৳৮,০০০+',
                'basis' => 'সাইট বদলে সময় নষ্ট বন্ধ + কোনো অর্ডার মিস না — দিনে ৩০–৪৫ মিনিট × ৩০ দিন',
                'compare' => 'সাবস্ক্রিপশন শুরু মাত্র ৳৯৯৯/মাস — একাধিক সাইট চালানোর সুবিধার তুলনায় নগণ্য।',
            ],
            'highlights' => [
                '২–৩ বা আনলিমিটেড ওয়েবসাইট (প্ল্যান অনুযায়ী)',
                'সব সাইটের অর্ডার এক অ্যাপে',
                'QR স্ক্যানে কানেক্ট',
            ],
            'read_more' => [
                [
                    'title' => 'একাধিক সাইট চালান?',
                    'body' => 'প্রতিটি সাইটে আলাদা লগইন করা ঝামেলার। WooEasyLife-এ সব সাইট এক জায়গায়: অর্ডার, কুরিয়ার, ফ্রড, স্টাফ — এক ড্যাশবোর্ডেই।',
                ],
            ],
            'accent' => 'cyan',
            'feature_keys' => ['common_dashboard', 'multistore_order_notifications', 'one_click_app_connect', 'centralized_notifications'],
        ],
    ],

    'value_pillars' => [
        [
            'id' => 'ai',
            'badge' => 'এআই ইন্টিগ্রেটেড',
            'headline' => 'এআই দিয়ে অর্ডার — হাতে টাইপ করার দিন শেষ',
            'subheadline' => 'কাস্টমারের মেসেজ, ছবি বা অসম্পূর্ণ ঠিকানা থেকে স্মার্ট অর্ডার তৈরি। সময় বাঁচান, ভুল কমান।',
            'accent' => 'fuchsia',
            'feature_keys' => [
                'ai_text_order_create',
                'ai_image_to_order_create',
                'ai_incomplete_address_autocomplete',
                'ai_driven_customer_scoring',
                'customer_behavior_track',
            ],
        ],
        [
            'id' => 'multistore',
            'badge' => 'একাধিক ওয়েবসাইট',
            'headline' => 'একাধিক ওয়েবসাইট — সব আপনার আঙুলের ডগায়',
            'subheadline' => '২–৩টা বা আনলিমিটেড ওয়েবসাইট এক অ্যাপ ও এক ড্যাশবোর্ডে। সাইট বদলাতে সময় নষ্ট হবে না।',
            'accent' => 'sky',
            'feature_keys' => [
                'common_dashboard',
                'multistore_order_notifications',
                'one_click_app_connect',
                'cross_store_order_detection',
                'centralized_notifications',
                'customer_call_identifier',
            ],
        ],
        [
            'id' => 'team',
            'badge' => 'স্মার্ট টিম ম্যানেজমেন্ট',
            'headline' => 'টিম পারফরম্যান্স ট্র্যাক করুন — সবাই এক লাইনে',
            'subheadline' => 'রোল ভিত্তিক অ্যাক্সেস, ওয়েবসাইট অনুযায়ী টিম অ্যাসাইন ও কল/অর্ডার পারফরম্যান্স দেখুন।',
            'accent' => 'violet',
            'feature_keys' => [
                'admin_employee_manage',
                'call_history_with_duration',
                'customer_call_identifier',
                'order_source_identifier',
            ],
        ],
        [
            'id' => 'protection',
            'badge' => 'ফ্রড প্রোটেকশন',
            'headline' => 'অর্ডার কনফার্মের আগেই বুঝে নিন কাস্টমার কেমন',
            'subheadline' => 'কুরিয়ার ডাটা দিয়ে ফ্রড চেক, ডুপ্লিকেট ব্লক আর চেকআউট প্রোটেকশন — রিটার্ন কমান, লাভ বাড়ান।',
            'accent' => 'emerald',
            'feature_keys' => [
                'fraud_customer_checker',
                'checkout_otp_validation',
                'checkout_bd_area_fields',
                'checkout_form_validation',
                'duplicate_order_validation',
                'duplicate_order_otp_soft_pass',
                'checkout_attempt_throttle',
                'daily_order_limit',
                'ip_block',
                'phone_email_block',
                'device_block',
                'bd_ip_restriction',
                'store_api_checkout_protection',
                'customer_blacklist',
            ],
        ],
        [
            'id' => 'automation',
            'badge' => 'ফুল অটোমেশন',
            'headline' => 'কুরিয়ার, এসএমএস, ইনভয়েস — সব অটো',
            'subheadline' => 'Steadfast, Pathao, RedX এক ক্লিকে। অর্ডার কনফার্ম হলেই কুরিয়ার এন্ট্রি, এসএমএস ও স্ট্যাটাস সিঙ্ক।',
            'accent' => 'amber',
            'feature_keys' => [
                'three_courier_partner_integration',
                'courier_entry_automation',
                'courier_auto_status_sync',
                'customer_sms_for_order',
                'parcel_note_history',
                'invoice_print',
                'missing_order_one_click_create',
            ],
        ],
    ],

    'feature_icons' => [
        'fraud_customer_checker' => 'shield',
        'pixel_protection' => 'spark',
        'three_courier_partner_integration' => 'truck',
        'courier_entry_automation' => 'truck',
        'courier_auto_status_sync' => 'truck',
        'courier_webhook_integrations' => 'truck',
        'parcel_note_history' => 'clipboard',
        'customer_sms_for_order' => 'message',
        'bulk_sms' => 'message',
        'duplicate_order_validation' => 'clipboard',
        'checkout_form_validation' => 'clipboard',
        'checkout_otp_validation' => 'shield',
        'checkout_bd_area_fields' => 'clipboard',
        'duplicate_order_otp_soft_pass' => 'shield',
        'checkout_attempt_throttle' => 'shield',
        'daily_order_limit' => 'clipboard',
        'ai_text_order_create' => 'spark',
        'ai_image_to_order_create' => 'spark',
        'invoice_print' => 'print',
        'one_click_app_connect' => 'phone',
        'multistore_order_notifications' => 'phone',
        'customer_call_identifier' => 'phone',
        'common_dashboard' => 'dashboard',
        'admin_employee_manage' => 'users',
        'call_history_with_duration' => 'phone',
        'order_source_identifier' => 'clipboard',
    ],

    'groups' => [
        'Courier' => 'কুরিয়ার অটোমেশন',
        'Customer' => 'কাস্টমার ইন্টেলিজেন্স',
        'SMS' => 'এসএমএস নোটিফিকেশন',
        'AI Featuree' => 'এআই ফিচার',
        'Checkout' => 'চেকআউট সুরক্ষা',
        'Orders' => 'অর্ডার ম্যানেজমেন্ট',
        'Block & restrict' => 'ফ্রড ও ব্লক',
        'Tools' => 'বিজনেস টুলস',
        'Print' => 'প্রিন্ট ও ইনভয়েস',
        'App' => 'মোবাইল অ্যাপ',
    ],

    'labels' => [
        'fraud_customer_checker' => 'ফ্রড কাস্টমার চেকার',
        'three_courier_partner_integration' => '৩টি কুরিয়ার পার্টনার ইন্টিগ্রেশন',
        'courier_entry_automation' => 'কুরিয়ার এন্ট্রি অটোমেশন',
        'customer_delivery_history' => 'কাস্টমার ডেলিভারি হিস্ট্রি',
        'parcel_note_history' => 'পার্সেল নোট হিস্ট্রি',
        'customer_sms_for_order' => 'অর্ডার এসএমএস নোটিফিকেশন',
        'bulk_sms' => 'বাল্ক এসএমএস',
        'ai_text_order_create' => 'টেক্সট থেকে অর্ডার তৈরি',
        'ai_image_to_order_create' => 'ছবি থেকে অর্ডার তৈরি',
        'ai_incomplete_address_autocomplete' => 'অসম্পূর্ণ ঠিকানা অটো-কমপ্লিট',
        'ai_driven_customer_scoring' => 'এআই কাস্টমার স্কোরিং',
        'daily_order_limit' => 'দৈনিক অর্ডার লিমিট',
        'checkout_form_validation' => 'চেকআউট ফর্ম ভ্যালিডেশন',
        'duplicate_order_validation' => 'ডুপ্লিকেট অর্ডার ব্লক',
        'checkout_otp_validation' => 'চেকআউট ওটিপি ভেরিফিকেশন',
        'checkout_bd_area_fields' => 'BD এরিয়া ফিল্ড (চেকআউট)',
        'duplicate_order_otp_soft_pass' => 'ডুপ্লিকেট কার্ট OTP soft-pass',
        'checkout_attempt_throttle' => 'চেকআউট অ্যাটেম্পট থ্রোটল',
        'ip_block' => 'আইপি ব্লক',
        'phone_email_block' => 'ফোন / ইমেইল ব্লক',
        'device_block' => 'ডিভাইস ব্লক',
        'bd_ip_restriction' => 'শুধু বাংলাদেশি আইপি',
        'store_api_checkout_protection' => 'স্টোর API চেকআউট প্রোটেকশন',
        'custom_status_manage' => 'কাস্টম স্ট্যাটাস ম্যানেজ',
        'customer_blacklist' => 'কাস্টমার ব্ল্যাকলিস্ট',
        'pixel_protection' => 'ফেসবুক অ্যাড আরও স্মার্ট করুন',
        'marketing_tools' => 'মার্কেটিং টুলস',
        'database_migration' => 'ডাটাবেস মাইগ্রেশন',
        'missing_orders' => 'মিসিং অর্ডার খুঁজুন',
        'missing_order_one_click_create' => 'ওয়ান ক্লিক মিসিং অর্ডার',
        'pos_sticker_print' => 'POS স্টিকার প্রিন্ট',
        'invoice_print' => 'ইনভয়েস প্রিন্ট',
        'order_cloning' => 'অর্ডার ক্লোনিং',
        'customer_behavior_track' => 'কাস্টমার বিহেভিয়ার ট্র্যাক',
        'repeat_customer_identifier' => 'রিপিট কাস্টমার শনাক্ত',
        'order_source_identifier' => 'অর্ডারে স্টাফ অ্যাসাইন ও সোর্স',
        'inline_shipping_change' => 'ইনলাইন কুরিয়ার পরিবর্তন',
        'order_note_management' => 'অর্ডার নোট ম্যানেজমেন্ট',
        'cod_change' => 'COD পরিবর্তন',
        'ordered_product_management' => 'অর্ডার প্রোডাক্ট ম্যানেজ',
        'order_edit_product_variation' => 'ভ্যারিয়েশন সহ অর্ডার এডিট',
        'quick_action_tool' => 'কুইক অ্যাকশন টুল',
        'courier_auto_status_sync' => 'কুরিয়ার অটো স্ট্যাটাস সিঙ্ক',
        'courier_webhook_integrations' => 'কুরিয়ার ওয়েবহুক',
        'one_click_app_connect' => 'ওয়ান ক্লিক অ্যাপ কানেক্ট',
        'multistore_order_notifications' => 'মাল্টি-স্টোর অর্ডার অ্যালার্ট',
        'customer_call_identifier' => 'কাস্টমার কল শনাক্ত',
        'cross_store_order_detection' => 'ক্রস-স্টোর অর্ডার ডিটেকশন',
        'call_history_with_duration' => 'কল হিস্ট্রি ও ডিউরেশন (অ্যাপ)',
        'common_dashboard' => 'সব ওয়েবসাইটের এক ড্যাশবোর্ড',
        'courier_movement_notification' => 'কুরিয়ার মুভমেন্ট অ্যালার্ট',
        'notification_sound_management' => 'নোটিফিকেশন সাউন্ড কন্ট্রোল',
        'centralized_notifications' => 'সেন্ট্রাল নোটিফিকেশন',
        'admin_employee_manage' => 'স্মার্ট টিম ম্যানেজমেন্ট',
    ],

    'plugin_feature_groups' => [
        'three_courier_partner_integration' => 'Courier',
        'courier_entry_automation' => 'Courier',
        'courier_auto_status_sync' => 'Courier',
        'courier_webhook_integrations' => 'Courier',
        'parcel_note_history' => 'Courier',
        'fraud_customer_checker' => 'Customer',
        'customer_delivery_history' => 'Customer',
        'customer_behavior_track' => 'Customer',
        'repeat_customer_identifier' => 'Customer',
        'customer_sms_for_order' => 'SMS',
        'bulk_sms' => 'SMS',
        'ai_text_order_create' => 'AI Featuree',
        'ai_image_to_order_create' => 'AI Featuree',
        'ai_incomplete_address_autocomplete' => 'AI Featuree',
        'ai_driven_customer_scoring' => 'AI Featuree',
        'checkout_form_validation' => 'Checkout',
        'duplicate_order_validation' => 'Orders',
        'checkout_otp_validation' => 'Checkout',
        'checkout_bd_area_fields' => 'Checkout',
        'duplicate_order_otp_soft_pass' => 'Checkout',
        'checkout_attempt_throttle' => 'Checkout',
        'ip_block' => 'Block & restrict',
        'phone_email_block' => 'Block & restrict',
        'device_block' => 'Block & restrict',
        'bd_ip_restriction' => 'Block & restrict',
        'store_api_checkout_protection' => 'Checkout',
        'daily_order_limit' => 'Checkout',
        'custom_status_manage' => 'Orders',
        'customer_blacklist' => 'Block & restrict',
        'pixel_protection' => 'Tools',
        'database_migration' => 'Tools',
        'marketing_tools' => 'Tools',
        'missing_orders' => 'Orders',
        'missing_order_one_click_create' => 'Orders',
        'pos_sticker_print' => 'Print',
        'invoice_print' => 'Print',
        'order_cloning' => 'Orders',
        'order_source_identifier' => 'Orders',
        'inline_shipping_change' => 'Orders',
        'order_note_management' => 'Orders',
        'cod_change' => 'Orders',
        'ordered_product_management' => 'Orders',
        'order_edit_product_variation' => 'Orders',
        'quick_action_tool' => 'Orders',
    ],

    'feature_descriptions' => [
        'fraud_customer_checker' => 'অর্ডার কনফার্মের আগেই কুরিয়ার ডাটা দিয়ে কাস্টমার যাচাই করে ফেক অর্ডার আটকান।',
        'three_courier_partner_integration' => 'Steadfast, Pathao, RedX — সব কুরিয়ার এক প্ল্যাটফর্ম থেকে ম্যানেজ করুন।',
        'courier_entry_automation' => 'অর্ডার কনফার্ম হলেই অটো কুরিয়ার এন্ট্রি — সময় বাঁচান।',
        'parcel_note_history' => 'কুরিয়ার পার্সেল নোট ও হিস্ট্রি এক জায়গায় দেখুন ও আপডেট করুন।',
        'customer_sms_for_order' => 'অর্ডার ও ডেলিভারি আপডেট কাস্টমারকে এসএমএসে পাঠান।',
        'duplicate_order_validation' => 'একই কাস্টমারের ডুপ্লিকেট অর্ডার ব্লক করে লস কমান।',
        'checkout_otp_validation' => 'চেকআউটে ফোন OTP ভেরিফিকেশন — ভুয়া নম্বরের অর্ডার আগেই আটকান। OTP SMS প্ল্যাটফর্ম ক্রেডিটে।',
        'checkout_bd_area_fields' => 'চেকআউটে Division → District → Upazila → Union পিকার — পরিষ্কার BD ঠিকানা, কম কুরিয়ার ফেইল।',
        'duplicate_order_otp_soft_pass' => 'ডুপ্লিকেট ব্লকের পর একবার OTP দিয়ে কার্ট চালিয়ে যেতে দিন (ডিফল্ট অফ)। দৈনিক লিমিট বাইপাস করে না।',
        'checkout_attempt_throttle' => 'একই ফোন/ডিভাইস দ্রুত চেকআউট রিট্রাই সীমিত করে বট হ্যামারিং কমায় (ডিফল্ট অফ)।',
        'checkout_form_validation' => 'নাম, ঠিকানা ও ফোন ফরম্যাট যাচাই + রেট লিমিট — জাঙ্ক অর্ডার কমে।',
        'daily_order_limit' => 'প্রতি কাস্টমার/নম্বরে দৈনিক অর্ডার লিমিট — স্প্যাম ও বারবার ফেক অর্ডার বন্ধ।',
        'ip_block' => 'সন্দেহজনক IP থেকে আসা অর্ডার ব্লক করুন।',
        'phone_email_block' => 'নির্দিষ্ট ফোন নম্বর বা ইমেইল ব্লক করে দিন।',
        'device_block' => 'ডিভাইস টোকেন দিয়ে বারবার ফেক অর্ডার দেওয়া ডিভাইস ব্লক করুন।',
        'bd_ip_restriction' => 'শুধু বাংলাদেশি IP থেকে অর্ডার — বিদেশি/ভিপিএন ট্রাফিক ব্লক।',
        'store_api_checkout_protection' => 'ব্লক/Store API চেকআউট বট ও অ্যাবিউজ থেকে সুরক্ষিত রাখুন।',
        'customer_blacklist' => 'সমস্যাযুক্ত কাস্টমারকে ম্যানুয়ালি ব্ল্যাকলিস্ট করে অর্ডার আটকান।',
        'pixel_protection' => 'কনফার্মড অর্ডারই Facebook-এ Purchase হিসেবে যায় — ফেক/ক্যানসেল বাদ, Ad Budget আসল ক্রেতাদের দিকে।',
        'one_click_app_connect' => 'QR স্ক্যানেই ওয়েবসাইট কানেক্ট — সেকেন্ডের মধ্যে প্রস্তুত।',
        'common_dashboard' => '২–৩ বা আনলিমিটেড ওয়েবসাইট এক ড্যাশবোর্ডে — সব আঙুলের ডগায়।',
        'multistore_order_notifications' => 'সব ওয়েবসাইটের নতুন অর্ডার এক অ্যাপে — কখনো মিস করবেন না।',
        'customer_call_identifier' => 'ইনকামিং কলেই কাস্টমার ও অর্ডার দেখুন — স্টাফ দ্রুত রেসপন্স দিতে পারে।',
        'cross_store_order_detection' => 'এক কাস্টমার অন্য ওয়েবসাইটেও অর্ডার দিয়েছে কিনা শনাক্ত করুন।',
        'call_history_with_duration' => 'কল হিস্ট্রি — অ্যাপ থেকে কল করলে প্রতিটি কলের ডিউরেশন রেকর্ড হয়।',
        'order_source_identifier' => 'অর্ডারে স্টাফ অ্যাসাইন ও সোর্স ট্র্যাক — কে প্রসেস করছে জানুন।',
        'courier_movement_notification' => 'কুরিয়ার মুভমেন্ট অ্যালার্ট সরাসরি ফোনে।',
        'notification_sound_management' => 'স্টোর অনুযায়ী আলাদা নোটিফিকেশন সাউন্ড।',
        'centralized_notifications' => 'সব ওয়েবসাইটের নোটিফিকেশন এক জায়গায় — কোথাও খুঁজতে হবে না।',
        'admin_employee_manage' => 'স্টাফ যোগ, রোল, ওয়েবসাইট অ্যাসাইন ও পোর্টাল — টিম এক জায়গায়।',
        'ai_text_order_create' => 'মেসেজ বা টেক্সট পেস্ট করলেই এআই অর্ডার বানায় — কপি-পেস্ট ঝামেলা নেই।',
        'ai_image_to_order_create' => 'কাস্টমারের স্ক্রিনশট বা ছবি থেকে এআই অর্ডার তৈরি করে।',
        'ai_incomplete_address_autocomplete' => 'অসম্পূর্ণ ঠিকানা এআই দিয়ে অটো পূরণ — ডেলিভারি ফেইল কমে।',
        'ai_driven_customer_scoring' => 'এআই কাস্টমার স্কোরিং — কে বিশ্বস্ত, কে রিস্কি তা দেখুন।',
        'customer_behavior_track' => 'কাস্টমারের আচরণ ট্র্যাক করে স্মার্ট সিদ্ধান্ত নিন।',
        'courier_auto_status_sync' => 'কুরিয়ার স্ট্যাটাস অটো সিঙ্ক — ম্যানুয়াল চেক লাগে না।',
        'invoice_print' => 'ওয়ান ক্লিকে ইনভয়েস ও স্টিকার প্রিন্ট করুন।',
        'missing_orders' => 'কার্টে রেখে/চেকআউট শুরু করে অর্ডার কমপ্লিট করেনি — এমন অসম্পূর্ণ অর্ডার আলাদা করে দেখুন।',
        'missing_order_one_click_create' => 'মিসিং অর্ডার ওয়ান ক্লিকে WooCommerce অর্ডারে রূপান্তর — হারানো বিক্রি ফিরিয়ে আনুন।',
        'pos_sticker_print' => 'POS স্টিকার প্রিন্ট — পার্সেল লেবেল সেকেন্ডে, প্যাকিং দ্রুত।',
        'order_cloning' => 'আগের অর্ডার ক্লোন করে দ্রুত নতুন অর্ডার তৈরি করুন।',
        'quick_action_tool' => 'বাল্ক অ্যাকশন — একসাথে অনেক অর্ডার হ্যান্ডেল করুন।',
        'inline_shipping_change' => 'অর্ডারে কুরিয়ার পরিবর্তন — প্যানেলে যেতে হয় না।',
        'order_note_management' => 'অর্ডার নোট ম্যানেজ — টিমের সাথে তথ্য শেয়ার সহজ।',
    ],

    'feature_detail_copy' => [
        'fraud_customer_checker' => [
            'summary' => 'কুরিয়ার ডাটা দিয়ে কাস্টমার যাচাই — অর্ডার কনফার্মের আগেই রিস্ক দেখুন।',
            'detail' => 'Steadfast, Pathao, RedX, Paperfly ও Carrybee — পাঁচটি কুরিয়ারের ডেলিভারি হিস্ট্রি, সাকসেস রেট আর ফ্রড নোট একসাথে।',
        ],
        'duplicate_order_validation' => [
            'summary' => 'একই কাস্টমারের ডুপ্লিকেট অর্ডার আগেই ব্লক করুন।',
            'detail' => 'একই ফোন, ইমেইল বা ডিভাইস থেকে একই কার্ট ২৪ ঘণ্টার মধ্যে এলে ব্লক — ক্যানসেল/ফেক অর্ডারও গণনায় থাকে। IP দিয়ে মিলানো হয় না।',
        ],
        'customer_delivery_history' => [
            'summary' => 'কাস্টমারের আগের ডেলিভারি রেকর্ড দেখুন।',
            'detail' => 'কতবার অর্ডার করেছে, কতবার ফেরত এসেছে — এক নজরে। ফ্রড চেকের সাথে মিলিয়ে সিদ্ধান্ত নিন।',
        ],
        'parcel_note_history' => [
            'summary' => 'পার্সেল নোট ও হিস্ট্রি এক জায়গায়।',
            'detail' => 'Steadfast পার্সেল নোট দেখুন ও মার্চেন্ট নোট আপডেট করুন — কুরিয়ার প্যানেলে আলাদা লগইন ছাড়াই।',
        ],
        'checkout_otp_validation' => [
            'summary' => 'চেকআউটে ওটিপি — ফেক অর্ডার কমে।',
            'detail' => 'Place order-এর আগে ফোন OTP যাচাই (ক্লাসিক + CartFlows)। সার্ভার-সাইড গেট বাইপাস রোধ করে। OTP SMS WooEasyLife প্ল্যাটফর্ম ক্রেডিটে — মার্চেন্ট SMS ব্যালেন্স কাটে না।',
        ],
        'checkout_bd_area_fields' => [
            'summary' => 'BD এরিয়া পিকার — ঠিকানা পরিষ্কার।',
            'detail' => 'বিলিং ও ডেলিভারি ঠিকানায় Division → District → Upazila → Union। প্রয়োজনে Required টগল। কুরিয়ার এন্ট্রির জন্য স্ট্রাকচার্ড ঠিকানা।',
        ],
        'duplicate_order_otp_soft_pass' => [
            'summary' => 'ডুপ্লিকেটের পর OTP soft-pass (ঐচ্ছিক)।',
            'detail' => 'ডুপ্লিকেট ব্লকের পর একবার OTP দিয়ে অর্ডার চালিয়ে যেতে দেয়। ডিফল্ট অফ। দৈনিক অর্ডার লিমিট বাইপাস করে না।',
        ],
        'checkout_attempt_throttle' => [
            'summary' => 'চেকআউট থ্রোটল — বট হ্যামারিং কমায়।',
            'detail' => 'একই ফোন বা ডিভাইসের দ্রুত রিট্রাই সীমিত করে। ডিফল্ট অফ — হ্যামারিং দেখলে চালু করুন।',
        ],
        'pixel_protection' => [
            'summary' => 'WooEasyLife কনফার্ম হওয়া অর্ডারই শুধু Facebook-এ Purchase হিসেবে পাঠায়।',
            'detail' => 'WooEasyLife কনফার্ম হওয়া অর্ডারই শুধু Facebook-এ Purchase হিসেবে পাঠায়। ফেক ও ক্যানসেল অর্ডার বাদ পড়ে, ফলে Pixel পরিষ্কার ডেটা পায়, Optimization আরও নির্ভুল হয় এবং Ad Budget আসল ক্রেতাদের দিকেই বেশি ব্যয় হয়।',
        ],
        'admin_employee_manage' => [
            'summary' => 'স্টাফ যোগ, রোল ও ওয়েবসাইট অ্যাসাইন।',
            'detail' => 'প্রতিটি এমপ্লয়ির রোল, কোন ওয়েবসাইটে কাজ করবে, পোর্টাল লগইন — এক জায়গায়। মালিক দেখেন কে কত অর্ডার হ্যান্ডেল করছে।',
        ],
        'call_history_with_duration' => [
            'summary' => 'কল হিস্ট্রি দেখুন — অ্যাপ থেকে কল করলে ডিউরেশন সহ।',
            'detail' => 'প্লাগইন/ড্যাশবোর্ডে কল লগ দেখা যায়। মোবাইল অ্যাপ দিয়ে কল করলে প্রতিটি কল কত মিনিট/সেকেন্ড স্থায়ী হয়েছিল তা সেভ হয় — স্টাফ পারফরম্যান্স ও ক্যানসেল বিশ্লেষণে কাজে লাগে।',
        ],
        'order_source_identifier' => [
            'summary' => 'অর্ডারে স্টাফ অ্যাসাইন — কে প্রসেস করছে জানুন।',
            'detail' => 'প্রতিটি অর্ডারে দায়িত্বশীল স্টাফ সেট করুন। Facebook, হোয়াটসঅ্যাপ বা ওয়েবসাইট — কোথা থেকে অর্ডার এসেছে ও কে হ্যান্ডেল করছে ট্র্যাক হয়। ক্যানসেল হলে কার দায়িত্বে ছিল দেখা যায়।',
        ],
        'customer_call_identifier' => [
            'summary' => 'কাস্টমার কল করলেই নাম ও অর্ডার হিস্ট্রি দেখা যায়।',
            'detail' => 'ইনকামিং কলে অ্যাপে কাস্টমারের নাম, আগের অর্ডার ও হিস্ট্রি সাথে সাথে দেখা যায়। স্টাফ দ্রুত রেসপন্স দিতে পারে, কলের সাথে অর্ডারও লিংক হয়—আলাদা করে খুঁজতে হয় না।',
        ],
        'three_courier_partner_integration' => [
            'summary' => 'Steadfast, Pathao, RedX এক প্ল্যাটফর্মে।',
            'detail' => 'আলাদা প্যানেলে লগইনের দিন শেষ — সব কুরিয়ার WooEasyLife থেকে।',
        ],
        'courier_entry_automation' => [
            'summary' => 'অর্ডার কনফার্ম = অটো কুরিয়ার এন্ট্রি।',
            'detail' => 'ম্যানুয়াল কপি-পেস্ট বাদ — স্ট্যাটাস বদলালেই কুরিয়ারে যায়।',
        ],
        'courier_auto_status_sync' => [
            'summary' => 'কুরিয়ার স্ট্যাটাস WooCommerce-এ অটো সিঙ্ক।',
            'detail' => 'ডেলিভারি/রিটার্ন আপডেট আলাদা চেক করতে হয় না।',
        ],
        'customer_sms_for_order' => [
            'summary' => 'অর্ডার ও ডেলিভারি আপডেট এসএমএস।',
            'detail' => 'কাস্টমারের «পার্সেল কোথায়?» কল কমে — ব্যবসার প্রফেশনাল ইম্প্রেশন তৈরি হয়।',
        ],
        'missing_orders' => [
            'summary' => 'অসম্পূর্ণ Checkout স্বয়ংক্রিয়ভাবে আলাদা লিস্টে জমা হয়।',
            'detail' => 'কাস্টমার কার্টে প্রোডাক্ট রেখে বা Checkout শুরু করেও অর্ডার সম্পন্ন না করলে, নির্দিষ্ট সময় পর সেটি Missing Order হিসেবে সংরক্ষণ হয়। কাস্টমারের নাম, ফোন নম্বর ও কার্টের সব তথ্য এক জায়গায় দেখতে পারবেন।',
        ],
        'missing_order_one_click_create' => [
            'summary' => 'মিসিং অর্ডার ওয়ান ক্লিকে তৈরি।',
            'detail' => 'কল বা ফ্রড চেকের পর কনফার্ম করলেই WooCommerce-এ অর্ডার — হারানো বিক্রি ফিরে আসে।',
        ],
        'order_cloning' => [
            'summary' => 'আগের অর্ডার ক্লোন করে দ্রুত নতুন অর্ডার।',
            'detail' => 'রিপিট কাস্টমার — এক ক্লিকে একই ডিটেইলস।',
        ],
        'quick_action_tool' => [
            'summary' => 'বাল্ক অ্যাকশন — অনেক অর্ডার একসাথে।',
            'detail' => 'স্ট্যাটাস, কুরিয়ার, প্রিন্ট — দ্রুত অপারেশন।',
        ],
        'pos_sticker_print' => [
            'summary' => 'POS স্টিকার — পার্সেল লেবেল প্রিন্ট।',
            'detail' => 'নাম, ঠিকানা, ফোন, COD — স্ট্যান্ডার্ড ফরম্যাটে, ভুল কমে।',
        ],
        'invoice_print' => [
            'summary' => 'ওয়ান ক্লিক ইনভয়েস প্রিন্ট।',
            'detail' => 'প্যাকিং ও রেকর্ডের জন্য প্রস্তুত ইনভয়েস।',
        ],
        'inline_shipping_change' => [
            'summary' => 'অর্ডারে কুরিয়ার পরিবর্তন — প্যানেলে যেতে হয় না।',
            'detail' => 'Steadfast থেকে Pathao — এক ক্লিকেই সুইচ।',
        ],
        'order_note_management' => [
            'summary' => 'অর্ডার নোট — টিম কমিউনিকেশন।',
            'detail' => 'কাস্টমারের বিশেষ চাহিদা, কলের ফলাফল — সব নোটে।',
        ],
        'ai_text_order_create' => [
            'summary' => 'মেসেজ পেস্ট — এআই অর্ডার বানায়।',
            'detail' => 'হাতে টাইপ ৫–১০ মিনিট → ৩০ সেকেন্ড।',
        ],
        'ai_image_to_order_create' => [
            'summary' => 'ছবি/স্ক্রিনশট থেকে এআই অর্ডার।',
            'detail' => 'কাস্টমার WhatsApp-এ ছবি পাঠাল — এআই সেটা পড়ে অর্ডার বানিয়ে দেয়।',
        ],
        'ai_incomplete_address_autocomplete' => [
            'summary' => 'অসম্পূর্ণ ঠিকানা এআই দিয়ে পূরণ।',
            'detail' => '«ঢাকা, গুলশান» — বাকি অংশ সাজেস্ট।',
        ],
        'ai_driven_customer_scoring' => [
            'summary' => 'এআই কাস্টমার রিস্ক স্কোর।',
            'detail' => 'কাকে ফোন-কনফার্ম, কাকে সরাসরি পার্সেল পাঠাবেন — ডাটা দিয়ে।',
        ],
        'common_dashboard' => [
            'summary' => 'সব ওয়েবসাইট এক ড্যাশবোর্ডে।',
            'detail' => '২–৩ বা আনলিমিটেড সাইট — এক লগইন, সব অর্ডার।',
        ],
        'multistore_order_notifications' => [
            'summary' => 'সব সাইটের অর্ডার নোটিফিকেশন এক অ্যাপে।',
            'detail' => 'কোন সাইট থেকে অর্ডার — আলাদা খোঁজার দরকার নেই।',
        ],
        'one_click_app_connect' => [
            'summary' => 'QR স্ক্যান — ওয়েবসাইট কানেক্ট।',
            'detail' => 'মিনিটের মধ্যে অ্যাপ + প্লাগইন লাইভ।',
        ],
        'centralized_notifications' => [
            'summary' => 'সেন্ট্রাল নোটিফিকেশন হাব।',
            'detail' => 'অর্ডার, কুরিয়ার, কল — এক জায়গায় অ্যালার্ট।',
        ],
    ],

    'feature_card_colors' => ['violet', 'emerald', 'sky', 'amber', 'rose', 'cyan', 'fuchsia', 'lime'],

    'stats' => [
        ['value' => '৳৪৫,০০০+', 'label' => 'মাসিক রিটার্ন লস বাঁচানোর উদাহরণ'],
        ['value' => '৩+ ঘণ্টা', 'label' => 'প্রতিদিন কুরিয়ার সময় সাশ্রয়'],
        ['value' => '৫ কুরিয়ার', 'label' => 'Pathao · Steadfast · RedX সহ'],
        ['value' => '১৪ দিন', 'label' => 'ফ্রি ট্রায়াল — কার্ড লাগবে না'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Courier performance dashboard (trust section)
    |--------------------------------------------------------------------------
    | Aggregate courier delivery numbers shown as social proof on the landing
    | page. IMPORTANT: replace the placeholder values below with your real
    | database totals so the numbers stay honest. `success_rate` is a number
    | (0-100) used for the progress bar width; all other values are display
    | strings (Bengali numerals recommended).
    */
    'courier_performance' => [
        'badge' => 'আমাদের ডাটাবেস',
        'headline' => 'অর্ডার পাঠানোর আগেই Courier History দেখুন',
        'subtitle' => 'লক্ষ লক্ষ কুরিয়ার ডেলিভারি রেকর্ড থেকে কাস্টমারের নম্বর দিয়ে মুহূর্তেই দেখুন—আগে কতটি অর্ডার সফলভাবে নিয়েছেন, আর কতটি রিটার্ন করেছেন।',
        'note' => '* ডাটা নিয়মিত আপডেট হয়; কুরিয়ার ও সময়ভেদে সংখ্যা ভিন্ন হতে পারে।',
        'kpis' => [
            ['label' => 'মোট পার্সেল যাচাই', 'value' => '১.২ কোটি+', 'tone' => 'neutral', 'icon' => 'box'],
            ['label' => 'সফল ডেলিভারি', 'value' => '১.১ কোটি+', 'tone' => 'safe', 'icon' => 'check'],
            ['label' => 'ক্যানসেল / রিটার্ন', 'value' => '১০ লক্ষ+', 'tone' => 'risky', 'icon' => 'x'],
            ['label' => 'গড় সফল ডেলিভারি', 'value' => '৯১.৫%', 'tone' => 'accent', 'icon' => 'trend'],
        ],
        'couriers' => [
            ['name' => 'Pathao', 'logo' => '/images/pathao.svg', 'delivered' => '৫৪ লক্ষ', 'returned' => '৪.১ লক্ষ', 'success_rate' => 93],
            ['name' => 'Steadfast', 'logo' => '/images/steadfast.svg', 'delivered' => '৪১ লক্ষ', 'returned' => '৩.৯ লক্ষ', 'success_rate' => 91],
            ['name' => 'RedX', 'logo' => '/images/redx.svg', 'delivered' => '১৭ লক্ষ', 'returned' => '২.১ লক্ষ', 'success_rate' => 88],
            ['name' => 'Carrybee', 'logo' => '/images/carrybee.svg', 'delivered' => '৮.৩ লক্ষ', 'returned' => '১.২ লক্ষ', 'success_rate' => 87],
            ['name' => 'Paperfly', 'logo' => '/images/paperfly.png', 'delivered' => '৫.৭ লক্ষ', 'returned' => '১.১ লক্ষ', 'success_rate' => 81],
        ],
    ],

    'loss_comparison' => [
        'headline' => 'রিটার্নেই কি প্রতি মাসে হাজার হাজার টাকা লস হচ্ছে?',
        'subtitle' => 'হাতে-কলমে কাজ নাকি স্মার্ট অটোমেশন—মাস শেষে পার্থক্যটা কত?',
        'without' => [
            'title' => 'WooEasyLife ছাড়া',
            'items' => [
                'ফেক অর্ডারের রিটার্নে মাসে ৳২০,০০০–৪৫,০০০+ লস',
                'প্রতিটি অর্ডার কুরিয়ারে হাতে এন্ট্রি—দিনে ৩+ ঘণ্টা সময় নষ্ট',
                'ডুপ্লিকেট অর্ডারে অপ্রয়োজনীয় প্যাকেজিং ও সময়ের অপচয়',
                'একাধিক সাইটের ঝামেলায় অর্ডার ম্যানেজ করাই কঠিন হয়ে যাচ্ছে',
                'কার্টে রেখে চলে যাওয়া কাস্টমারদের বেশিরভাগই আর ফিরে আসে না',
            ],
            'summary' => 'মাসে হাজার হাজার টাকা অকারণে খরচ',
        ],
        'with' => [
            'title' => 'WooEasyLife দিয়ে',
            'items' => [
                'Courier History দেখে অর্ডার পাঠান—রিটার্ন কমান',
                'এক ক্লিকে কনফার্ম, সঙ্গে সঙ্গে কুরিয়ারে এন্ট্রি',
                'ঝুঁকিপূর্ণ বা ডুপ্লিকেট অর্ডার আগে থেকেই শনাক্ত করুন',
                'অর্ডার আসার সঙ্গে সঙ্গেই ফোনে নোটিফিকেশন পান',
                'এক জায়গা থেকে সব ওয়েবসাইট নিয়ন্ত্রণ করুন',
                'কার্টে রেখে যাওয়া কাস্টমারদের ফিরিয়ে আনুন—Ad Cost বাঁচান',
            ],
            'summary' => 'ফেক অর্ডার কমে, সফল ডেলিভারি বাড়ে—লাভও বাড়ে',
        ],
    ],

    'payment_methods' => ['bKash', 'Nagad', 'Rocket', 'Bank'],

    'enterprise_cta' => [
        'title' => 'বড় টিম বা একাধিক ব্র্যান্ড?',
        'subtitle' => 'আরও ওয়েবসাইট, কাস্টম লিমিট আর আলাদা সাপোর্ট — আপনার ব্যবসা অনুযায়ী প্ল্যান সাজিয়ে দেব।',
        'button_label' => 'হোয়াটসঅ্যাপে কথা বলুন',
    ],

    'integrations' => [
        'badge' => 'যাদের সাথে কাজ করে',
        'headline' => 'আপনার ব্যবসার টুলগুলোর সাথে মিলে যায়',
        'subtitle' => 'ওয়েবসাইট থেকে কুরিয়ার, এসএমএস ও মোবাইল অ্যাপ — সব এক জায়গায়।',
        'items' => [
            ['icon' => 'woocommerce', 'title' => 'WooCommerce ওয়েবসাইট', 'description' => 'সাইটে যোগ করুন — অর্ডার অটো চলে আসে।', 'accent' => 'fuchsia'],
            ['icon' => 'courier', 'title' => 'Pathao · Steadfast · RedX', 'description' => 'কনফার্ম হলেই অটো কুরিয়ার এন্ট্রি ও আপডেট।', 'accent' => 'amber'],
            ['icon' => 'sms', 'title' => 'এসএমএস', 'description' => 'অর্ডার ও ডেলিভারি আপডেট সরাসরি কাস্টমারের ফোনে।', 'accent' => 'emerald'],
            ['icon' => 'app', 'title' => 'মোবাইল অ্যাপ (Android)', 'description' => 'সব সাইট এক অ্যাপে — নতুন অর্ডার অ্যালার্ট ও কল।', 'accent' => 'sky'],
            ['icon' => 'ai', 'title' => 'মেসেজ থেকে অর্ডার', 'description' => 'টেক্সট বা ছবি দিলেই দ্রুত অর্ডার তৈরি।', 'accent' => 'violet'],
            ['icon' => 'api', 'title' => 'bKash · Nagad · Rocket', 'description' => 'সহজ পেমেন্ট — সাবস্ক্রিপশন দ্রুত চালু।', 'accent' => 'rose'],
        ],
    ],

    'fraud_check' => [
        'enabled' => env('LANDING_FRAUD_CHECK_ENABLED', true),
        'daily_free_limit' => (int) env('LANDING_FRAUD_CHECK_DAILY_LIMIT', 5),

        // Social-proof baseline for the "আজকে X বার সার্চ হয়েছে" counter so it
        // never shows an empty/dead "0". The displayed number = a time-weighted
        // slice of this base (grows through the day) + real searches. Set to 0
        // to show only the real count.
        'daily_search_base' => (int) env('LANDING_FRAUD_DAILY_SEARCH_BASE', 500),

        // Sample report shown in the fraud tool before the user searches, so the
        // value is visible immediately. Clearly labelled as an example in the UI.
        'demo' => [
            'phone_masked' => '017********',
            'risk_label' => 'নিরাপদ গ্রাহক',
            'risk_tone' => 'safe',
            'total_order' => 42,
            'confirmed' => 38,
            'cancel' => 4,
            'success_rate' => '৯০%',
            'couriers' => [
                ['title' => 'Steadfast', 'confirmed' => 15, 'cancel' => 1, 'success_rate' => '৯৪%'],
                ['title' => 'Pathao', 'confirmed' => 10, 'cancel' => 1, 'success_rate' => '৯১%'],
                ['title' => 'RedX', 'confirmed' => 6, 'cancel' => 1, 'success_rate' => '৮৬%'],
                ['title' => 'Paperfly', 'confirmed' => 4, 'cancel' => 1, 'success_rate' => '৮০%'],
                ['title' => 'Carrybee', 'confirmed' => 3, 'cancel' => 0, 'success_rate' => '১০০%'],
            ],
        ],
    ],
];
