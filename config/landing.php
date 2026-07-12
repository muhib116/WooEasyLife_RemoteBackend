<?php

return [
    'whatsapp_phone' => env('LANDING_WHATSAPP_PHONE', '8801770989591'),

    'whatsapp_default_message' => env(
        'LANDING_WHATSAPP_DEFAULT_MESSAGE',
        'সালাম, আমি WooEasyLife সাবস্ক্রিপশন নিতে চাই।',
    ),

    'helpline_phone' => env('LANDING_HELPLINE_PHONE', env('LANDING_WHATSAPP_PHONE', '8801770989591')),

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
        'free_trial_hint' => 'ফ্রি ট্রায়াল শুরু করতে লগইন করুন অথবা WhatsApp-এ যোগাযোগ করুন।',
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
        ['icon' => 'lock', 'label' => 'bKash · Nagad · Rocket'],
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
        'badge' => 'বাংলাদেশের COD অনলাইন ব্যবসার জন্য',
        'headline' => 'ফেক অর্ডারে টাকা নষ্ট হচ্ছে?',
        'headline_accent' => 'আগে চেক করুন — তারপর কনফার্ম করুন।',
        'subheadline' => 'অর্ডার পাঠানোর আগেই কাস্টমারের কুরিয়ার রেকর্ড দেখুন। কুরিয়ার এন্ট্রি অটো হোক। হারিয়ে যাওয়া অর্ডার ফেরান। মাসে হাজার হাজার টাকা বাঁচান।',
    ],

    'hero_trust_badges' => [
        '১৪ দিন ফ্রি ট্রায়াল',
        'কোনো কার্ড লাগবে না',
        'bKash · Nagad · Rocket',
        'হোয়াটসঅ্যাপ সাপোর্ট',
    ],

    'hero_bullets' => [
        'দিনে ১০টা ফেক অর্ডার আটকালেই ≈ মাসে ৳৪৫,০০০+ রিটার্ন লস বাঁচে',
        'কুরিয়ার প্যানেলে বসবেন না — অর্ডার কনফার্ম হলেই অটো এন্ট্রি (দিনে ৩+ ঘণ্টা)',
        'কার্টে রেখে চলে যাওয়া কাস্টমারকে ফোন করে বিক্রি ফেরান',
    ],

    'roi_scenarios' => [
        [
            'icon' => '💸',
            'title' => 'রিটার্ন লস কমান',
            'calculation' => 'প্রতি রিটার্ন ≈ ৳১৫০–৩০০ · দিনে ১০টা ফেক অর্ডার = মাসে ৳৪৫,০০০+',
            'benefit' => 'শুধু অর্ধেক কমালেই মাসিক সাবস্ক্রিপশনের খরচ উঠে আসে',
            'accent' => 'rose',
        ],
        [
            'icon' => '⏱️',
            'title' => 'কুরিয়ার সময় বাঁচান',
            'calculation' => 'প্রতি অর্ডারে ৪ মিনিট × ৫০ অর্ডার = দিনে ৩+ ঘণ্টা',
            'benefit' => 'অটো এন্ট্রিতে স্টাফ অন্য কাজে লাগে — বেতনের হিসাবেই সাশ্রয়',
            'accent' => 'amber',
        ],
        [
            'icon' => '📱',
            'title' => 'হারানো অর্ডার ফেরান',
            'calculation' => 'দিনে ৫টা অর্ডার ফেরান × গড় ৳৫০০ = ৳২,৫০০/দিন (মাসে ৳৭৫,০০০+)',
            'benefit' => 'অ্যাড খরচ আগেই দিয়েছেন — শুধু বিক্রিটা হাতছাড়া হচ্ছিল',
            'accent' => 'sky',
        ],
        [
            'icon' => '📦',
            'title' => 'প্যাকিং দ্রুত করুন',
            'calculation' => 'হাতে লেবেল ২ মিনিট × ৫০ পার্সেল = দিনে ~১০০ মিনিট',
            'benefit' => 'প্রিন্ট করে স্টিকার লাগান — ভুল ঠিকানা ও রিটার্ন কমে',
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
        'headline' => 'আপনার ব্যবসায় মাসে কত টাকা বাঁচতে পারে?',
        'subtitle' => 'দৈনিক অর্ডার আর রিটার্ন রেট দিন — নিচের সংখ্যা আপনার হিসাবে বদলাবে।',
        'note' => '* আনুমানিক হিসাব; আপনার প্রকৃত সংখ্যা ভিন্ন হতে পারে। মাসে ৩০ দিন ধরা হয়েছে।',
        'subscription_note' => 'সাবস্ক্রিপশন শুরু মাত্র ৳৯৯৯/মাস — উপরের সাশ্রয়ের তুলনায় খুবই কম।',
        'days_per_month' => 30,
        // Estimated share of returns WooEasyLife's fraud check + automation prevents.
        'reduction_percent' => 40,
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

    'how_it_works' => [
        [
            'step' => '০১',
            'title' => 'ফ্রি ট্রায়াল শুরু করুন',
            'description' => '১৪ দিন বিনামূল্যে ব্যবহার করুন — হোয়াটসঅ্যাপেও সাহায্য পাবেন।',
        ],
        [
            'step' => '০২',
            'title' => 'ওয়েবসাইটে যোগ করুন',
            'description' => 'আপনার WooCommerce সাইটে WooEasyLife যোগ করুন — কয়েক মিনিটেই প্রস্তুত।',
        ],
        [
            'step' => '০৩',
            'title' => 'ফ্রড চেক ও কুরিয়ার চালু',
            'description' => 'অর্ডার পাঠানোর আগে চেক করুন, কুরিয়ার এন্ট্রি অটো রাখুন — লস কমতে শুরু করবে।',
        ],
        [
            'step' => '০৪',
            'title' => 'ফোনেই সব দেখুন',
            'description' => 'মোবাইল অ্যাপে নতুন অর্ডার, কল ও আপডেট — বাইরে থাকলেও ব্যবসা চলবে।',
        ],
    ],

    'app_showcase' => [
        'headline' => 'পুরো ব্যবসা এখন আপনার পকেটে',
        'subheadline' => 'নতুন অর্ডার এলেই ফোনে নোটিফিকেশন। হারিয়ে যাওয়া অর্ডারও এক জায়গায়। বাইরে থাকলেও একটাও মিস হবে না।',
        'screenshot' => '/images/woo-easy-life/app-left-sidebar.png',
        'screenshot_alt' => 'WooEasyLife মোবাইল অ্যাপ — অর্ডার ও ড্যাশবোর্ড',
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
        'headline' => 'অর্ডার পাঠানোর আগেই জানুন — কাস্টমার বিশ্বস্ত না ঝুঁকিপূর্ণ',
        'subtitle' => 'একবার পার্সেল চলে গেলে রিটার্নের খরচ আপনার ঘাড়ে। কুরিয়ার রেকর্ড দেখে আগেই সিদ্ধান্ত নিন।',
        'cards' => [
            [
                'icon' => '📊',
                'title' => 'ডেলিভারি হার দেখুন',
                'description' => 'Pathao, Steadfast, RedX সহ সব কুরিয়ারে কতবার নিয়েছে, কতবার ফেরত — এক নজরে।',
            ],
            [
                'icon' => '✅',
                'title' => 'আগের অর্ডারের রেকর্ড',
                'description' => 'এই নম্বরে আগে কতবার অর্ডার হয়েছে আর কতবার সফল — সব একসাথে।',
            ],
            [
                'icon' => '🛡️',
                'title' => 'ফেক অর্ডার আগেই ধরুন',
                'description' => 'কম ডেলিভারি হার বা সতর্কবার্তা থাকলে কনফার্ম না করেই থামতে পারবেন।',
            ],
            [
                'icon' => '📱',
                'title' => 'এখনই ফ্রি চেক করুন',
                'description' => 'অ্যাকাউন্ট ছাড়াই নম্বর দিন — প্রতিদিন কয়েকটা ফ্রি চেক, সাথে সাথে ফলাফল।',
                'cta' => '#fraud-check',
                'cta_label' => 'ফ্রি ফ্রড চেক করুন',
            ],
        ],
    ],

    'feature_showcases' => [
        [
            'id' => 'fraud',
            'badge' => 'ফেক অর্ডার থেকে বাঁচুন',
            'headline' => 'ফেক অর্ডার একাধিক স্তরে আটকান',
            'teaser' => 'কুরিয়ার রেকর্ড + নম্বর যাচাই + অটো ব্লক',
            'pain' => 'ফেক অর্ডার একবার পাঠিয়ে দিলে রিটার্ন চার্জ, প্যাকেজিং আর সময় — সব আপনার পকেট থেকে যায়। অনেক ব্যবসায়ী মাসে লাখ টাকারও বেশি লস করেন।',
            'solution' => 'অর্ডার পাঠানোর আগেই কুরিয়ার রেকর্ড দেখুন। চেকআউটে নম্বর যাচাই, একই অর্ডার বারবার ব্লক, আর সমস্যাযুক্ত নম্বর বন্ধ রাখুন। ফেক অর্ডার শুরুতেই ঝরে যায়।',
            'benefit' => 'ঝুঁকিপূর্ণ অর্ডার আগেই আটকে যায় — রিটার্ন কমে, সফল ডেলিভারি বাড়ে, মাসে হাজার হাজার টাকা বাঁচে।',
            'profit' => [
                'monthly' => '৳৪৫,০০০+',
                'basis' => 'দিনে ১০টি ফেক অর্ডার ঠেকান × গড় ৳১৫০ রিটার্ন খরচ × ৩০ দিন',
                'compare' => 'সাবস্ক্রিপশন শুরু মাত্র ৳৯৯৯/মাস — শুধু এই এক কাজের সাশ্রয়েই কয়েকগুণ উঠে আসে।',
            ],
            'highlights' => [
                'কুরিয়ার ডেলিভারি হার দেখে সিদ্ধান্ত',
                'চেকআউটে নম্বর OTP যাচাই',
                'একই অর্ডার বারবার আসা বন্ধ',
                'সমস্যাযুক্ত নম্বর/ডিভাইস ব্লক',
                'শুধু বাংলাদেশ থেকে অর্ডার নেওয়ার অপশন',
            ],
            'read_more' => [
                [
                    'title' => 'ফ্রড চেক কেন দরকার?',
                    'body' => 'বাংলাদেশে ক্যাশ অন ডেলিভারি ব্যবসায় ফেক অর্ডার সবচেয়ে বড় মাথাব্যথা। একবার পার্সেল চলে গেলে রিটার্ন চার্জ, প্যাকেজিং আর সময় — সব লস। WooEasyLife-এ অর্ডার পাঠানোর আগেই কুরিয়ার রেকর্ড দেখে সিদ্ধান্ত নিন।',
                ],
                [
                    'title' => 'কী কী সুরক্ষা পাবেন?',
                    'body' => 'চেকআউটে: ফোন OTP, নাম-ঠিকানা-নম্বর যাচাই, একই অর্ডার ব্লক, দৈনিক অর্ডার সীমা। সুরক্ষায়: নম্বর/ইমেইল/ডিভাইস ব্লক, শুধু বাংলাদেশি অর্ডার, কাস্টম ব্ল্যাকলিস্ট। প্রতিটি অপশন এক ক্লিকে চালু/বন্ধ করা যায়।',
                ],
                [
                    'title' => 'ফ্রড চেক কীভাবে কাজ করে?',
                    'body' => 'কাস্টমারের নম্বর দিয়ে Pathao, Steadfast, RedX, Paperfly ও Carrybee — পাঁচটি কুরিয়ারের ডেলিভারি রেকর্ড একসাথে দেখুন। ঝুঁকি বেশি হলে কনফার্ম করবেন নাকি বাতিল — সিদ্ধান্ত পুরোপুরি আপনার হাতে।',
                ],
            ],
            'accent' => 'emerald',
            'feature_keys' => ['fraud_customer_checker', 'checkout_otp_validation', 'checkout_form_validation', 'duplicate_order_validation', 'daily_order_limit', 'ip_block', 'phone_email_block', 'device_block', 'bd_ip_restriction', 'store_api_checkout_protection', 'customer_blacklist'],
        ],
        [
            'id' => 'pixel',
            'badge' => 'ফেসবুক অ্যাড বাঁচান',
            'headline' => 'ফেক অর্ডারে অ্যাড বাজেট নষ্ট হওয়া বন্ধ করুন',
            'teaser' => 'শুধু কনফার্মড অর্ডারকেই «বিক্রি» হিসেবে গুনবে Facebook',
            'pain' => 'সাধারণ সেটআপে প্রতিটি চেকআউটকেই বিক্রি ধরে নেয় Facebook — ফেক বা পরে ক্যানসেল হওয়া অর্ডারও। ফলে ভুল মানুষের কাছে অ্যাড যায়, অ্যাড খরচ বাড়ে, আসল বিক্রি কমে।',
            'solution' => 'WooEasyLife শুধু তখনই Facebook-কে «বিক্রি হয়েছে» বলে, যখন আপনি অর্ডার কনফার্ম করেন। ফেক অর্ডার অ্যাড শেখাতে পারে না — বাজেট নষ্ট হয় না।',
            'benefit' => 'Facebook আসল ক্রেতা শেখে — টার্গেটিং ভালো হয়, প্রতি আসল অর্ডারে অ্যাড খরচ কমে।',
            'profit' => [
                'monthly' => '৳২০,০০০+',
                'basis' => 'ফেক অর্ডারের নয়েজ বাদ দিয়ে অ্যাড খরচ ~২০% কমান — মাসে ৳১,০০,০০০ অ্যাডে ৳২০,০০০ সাশ্রয়',
                'compare' => 'সাবস্ক্রিপশন শুরু মাত্র ৳৯৯৯/মাস — শুধু অ্যাড সাশ্রয়েই বহুগুণ উঠে আসে।',
            ],
            'highlights' => [
                'শুধু কনফার্মড অর্ডারেই বিক্রি গোনা হয়',
                'ফেক/ক্যানসেল অর্ডার অ্যাড শেখায় না',
                'মোবাইল অ্যাড ব্লকার থাকলেও ডাটা মিস কম',
                'অ্যাড বাজেট আসল ক্রেতার পেছনে যায়',
            ],
            'read_more' => [
                [
                    'title' => 'ফেক অর্ডার অ্যাডের কী ক্ষতি করে?',
                    'body' => 'COD ব্যবসায় অনেক অর্ডার শেষ পর্যন্ত ফেক বা ক্যানসেল হয়। সাধারণ সেটআপ চেকআউটেই বিক্রি গুনে ফেলে, তাই Facebook ভাবে ওগুলোই সফল বিক্রি — আর সেই ধরনের লোকদের কাছে আপনার অ্যাড দেখাতে থাকে। ফলাফল: বেশি ফেক অর্ডার, বেশি অ্যাড খরচ, কম আসল বিক্রি।',
                ],
                [
                    'title' => 'সমাধান কীভাবে কাজ করে?',
                    'body' => '«বিক্রি হয়েছে» সিগনাল যায় শুধু অর্ডার কনফার্ম হলে। আপনি চাইলে অন্য স্ট্যাটাসও সেট করতে পারেন। ফলে Facebook শুধু আসল ক্রেতা শেখে — অ্যাড আরও সঠিক মানুষের কাছে যায়।',
                ],
            ],
            'accent' => 'fuchsia',
            'feature_keys' => ['pixel_protection'],
        ],
        [
            'id' => 'team',
            'badge' => 'স্টাফ ও কল ট্র্যাকিং',
            'headline' => 'ক্যানসেল হলে জানুন কেন — কে কল করেছিল, কতক্ষণ',
            'teaser' => 'কলের সময়, কোন স্টাফ, কোন অর্ডার — সব এক জায়গায়',
            'pain' => 'অর্ডার ক্যানসেল হলে বোঝা যায় না — স্টাফ ঠিকমতো কল করেছিল কিনা, কতক্ষণ কথা হয়েছিল। দোষ কার, সমস্যা কোথায় — অনুমানেই চলতে হয়।',
            'solution' => 'অ্যাপ দিয়ে কল করলে সময়কাল সেভ হয়। অর্ডারে স্টাফ অ্যাসাইন করুন — ক্যানসেল হলে কল + স্টাফ + কারণ একসাথে দেখুন।',
            'benefit' => 'ক্যানসেলের কারণ বোঝা যায়, স্টাফ ট্রেনিং সহজ হয়, ফেক কাস্টমার vs দুর্বল ফলো-আপ আলাদা করা যায়।',
            'profit' => [
                'monthly' => '৳১৫,০০০+',
                'basis' => 'সঠিক ফলো-আপে দিনে ৫টি অর্ডার বাঁচান × গড় ৳১০০ লাভ × ৩০ দিন',
                'compare' => 'সাবস্ক্রিপশন শুরু মাত্র ৳৯৯৯/মাস — একটা স্টাফের ভুলে হারানো অর্ডারই এর চেয়ে বেশি।',
            ],
            'highlights' => [
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
            'feature_keys' => ['admin_employee_manage', 'call_history_with_duration', 'order_source_identifier', 'customer_call_identifier'],
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
                    'body' => 'প্রতি অর্ডারে কুরিয়ার সাইটে লগইন, কপি-পেস্ট — এগুলো দিনে ঘণ্টা নষ্ট করে। WooEasyLife-এ স্ট্যাটাস বদলালেই কুরিয়ারে চলে যায়।',
                ],
                [
                    'title' => 'এসএমএস কেন দরকার?',
                    'body' => 'কাস্টমারকে অর্ডার ও ডেলিভারি আপডেট এসএমএসে যায় — «কোথায় আমার পার্সেল?» কল কমে।',
                ],
            ],
            'accent' => 'amber',
            'feature_keys' => ['three_courier_partner_integration', 'courier_entry_automation', 'courier_auto_status_sync', 'customer_sms_for_order'],
        ],
        [
            'id' => 'missing',
            'badge' => 'হারানো বিক্রি ফেরান',
            'headline' => 'কার্টে রেখে চলে গেছে, অর্ডার শেষ করেনি?',
            'teaser' => 'অসম্পূর্ণ অর্ডার ধরে এক ক্লিকে বিক্রি ফেরান',
            'pain' => 'অনেক কাস্টমার প্রোডাক্ট কার্টে রেখে বা অর্ডার শুরু করে শেষ করে না। এই সম্ভাব্য বিক্রি চোখেই পড়ে না — নীরবে হাতছাড়া হয়ে যায়।',
            'solution' => 'অসম্পূর্ণ অর্ডার আলাদা লিস্টে জমা হয় — নাম, নম্বর ও প্রোডাক্ট সহ। কল করুন, ফ্রড চেক করুন, তারপর এক ক্লিকে অর্ডার বানান।',
            'benefit' => 'হারানো বিক্রি ফিরে আসে — অ্যাড খরচ আগেই দিয়েছেন, শুধু বিক্রিটা হাতছাড়া হচ্ছিল।',
            'profit' => [
                'monthly' => '৳৭৫,০০০+',
                'basis' => 'দিনে ৫টি অসম্পূর্ণ অর্ডার ফেরান × গড় ৳৫০০ × ৩০ দিন',
                'highlight' => 'এই অর্ডারগুলোর জন্য নতুন করে এক টাকাও অ্যাড খরচ নেই — অ্যাড খরচ তো আগেই দিয়েছেন।',
                'compare' => 'সাবস্ক্রিপশন শুরু মাত্র ৳৯৯৯/মাস — একদিনের ফেরানো অর্ডারেই তা উঠে আসে।',
            ],
            'highlights' => [
                'অসম্পূর্ণ অর্ডার অটো ধরা পড়ে',
                'সরাসরি কল, ফ্রড চেক ও এক ক্লিকে অর্ডার',
                'হারানো vs ফেরানো টাকা ড্যাশবোর্ডে দেখুন',
            ],
            'read_more' => [
                [
                    'title' => 'হারানো অর্ডার আসলে কী?',
                    'body' => 'কাস্টমার কার্টে প্রোডাক্ট নিল বা অর্ডার ফর্ম পূরণ শুরু করল, কিন্তু শেষ পর্যন্ত অর্ডার করল না — এগুলোই হারানো অর্ডার। WooEasyLife এগুলো আলাদা লিস্টে রাখে, যাতে একটাও সম্ভাব্য বিক্রি হারিয়ে না যায়।',
                ],
                [
                    'title' => 'কীভাবে ফেরাবেন?',
                    'body' => 'প্রতিটিতে নাম, নম্বর ও প্রোডাক্ট দেখা যায়। এক ক্লিকে ফোন বা ফ্রড চেক; কনফার্ম করলেই অর্ডার তৈরি হয়ে যায়।',
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
                'checkout_form_validation',
                'duplicate_order_validation',
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
        'customer_sms_for_order' => 'message',
        'bulk_sms' => 'message',
        'duplicate_order_validation' => 'clipboard',
        'checkout_form_validation' => 'clipboard',
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
        'ip_block' => 'আইপি ব্লক',
        'phone_email_block' => 'ফোন / ইমেইল ব্লক',
        'device_block' => 'ডিভাইস ব্লক',
        'bd_ip_restriction' => 'শুধু বাংলাদেশি আইপি',
        'store_api_checkout_protection' => 'স্টোর API চেকআউট প্রোটেকশন',
        'custom_status_manage' => 'কাস্টম স্ট্যাটাস ম্যানেজ',
        'customer_blacklist' => 'কাস্টমার ব্ল্যাকলিস্ট',
        'pixel_protection' => 'মেটা পিক্সেল প্রোটেকশন',
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
        'inline_shipping_change' => 'ইনলাইন শিপিং পরিবর্তন',
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
        'customer_sms_for_order' => 'অর্ডার ও ডেলিভারি আপডেট কাস্টমারকে এসএমএসে পাঠান।',
        'duplicate_order_validation' => 'একই কাস্টমারের ডুপ্লিকেট অর্ডার ব্লক করে লস কমান।',
        'checkout_otp_validation' => 'চেকআউটে ফোন OTP ভেরিফিকেশন — ভুয়া নম্বরের অর্ডার আগেই আটকান।',
        'checkout_form_validation' => 'নাম, ঠিকানা ও ফোন ফরম্যাট যাচাই + রেট লিমিট — জাঙ্ক অর্ডার কমে।',
        'daily_order_limit' => 'প্রতি কাস্টমার/নম্বরে দৈনিক অর্ডার লিমিট — স্প্যাম ও বারবার ফেক অর্ডার বন্ধ।',
        'ip_block' => 'সন্দেহজনক IP থেকে আসা অর্ডার ব্লক করুন।',
        'phone_email_block' => 'নির্দিষ্ট ফোন নম্বর বা ইমেইল ব্লক করে দিন।',
        'device_block' => 'ডিভাইস টোকেন দিয়ে বারবার ফেক অর্ডার দেওয়া ডিভাইস ব্লক করুন।',
        'bd_ip_restriction' => 'শুধু বাংলাদেশি IP থেকে অর্ডার — বিদেশি/ভিপিএন ট্রাফিক ব্লক।',
        'store_api_checkout_protection' => 'ব্লক/Store API চেকআউট বট ও অ্যাবিউজ থেকে সুরক্ষিত রাখুন।',
        'customer_blacklist' => 'সমস্যাযুক্ত কাস্টমারকে ম্যানুয়ালি ব্ল্যাকলিস্ট করে অর্ডার আটকান।',
        'pixel_protection' => 'Meta Pixel + Conversions API — Purchase ইভেন্ট শুধু Confirmed অর্ডারে পাঠায়, ফেক অর্ডারে পিক্সেল নষ্ট হয় না, অ্যাড খরচ কমে।',
        'one_click_app_connect' => 'QR স্ক্যানেই ওয়েবসাইট কানেক্ট — সেকেন্ডের মধ্যে প্রস্তুত।',
        'common_dashboard' => '২–৩ বা আনলিমিটেড ওয়েবসাইট এক ড্যাশবোর্ডে — সব আঙুলের ডগায়।',
        'multistore_order_notifications' => 'সব ওয়েবসাইটের নতুন অর্ডার এক অ্যাপে — কখনো মিস করবেন না।',
        'customer_call_identifier' => 'ইনকামিং কলে কাস্টমার শনাক্ত — অর্ডার হিস্ট্রি সহ দ্রুত রেসপন্স।',
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
            'detail' => 'Steadfast, Pathao, RedX, Paperfly ও Carrybee — পাঁচটি কুরিয়ারের ডেলিভারি হিস্ট্রি, সাকসেস রেট আর ফ্রড নোট একসাথে। ল্যান্ডিং পেজেই ফ্রি চেক — রেজিস্ট্রেশন ছাড়াই।',
        ],
        'duplicate_order_validation' => [
            'summary' => 'একই কাস্টমারের ডুপ্লিকেট অর্ডার আগেই ব্লক করুন।',
            'detail' => 'আগে অর্ডার দিয়েছেন এমন কাস্টমার আবার অর্ডার দিলে অ্যালার্ট বা ব্লক — অপ্রয়োজনীয় প্যাকেজিং লস কমে।',
        ],
        'customer_delivery_history' => [
            'summary' => 'কাস্টমারের আগের ডেলিভারি রেকর্ড দেখুন।',
            'detail' => 'কতবার অর্ডার করেছে, কতবার ফেরত এসেছে — এক নজরে। ফ্রড চেকের সাথে মিলিয়ে সিদ্ধান্ত নিন।',
        ],
        'checkout_otp_validation' => [
            'summary' => 'চেকআউটে ওটিপি — ফেক অর্ডার কমে।',
            'detail' => 'অর্ডার করার সময় ফোন নম্বর ভেরিফাই হয় — ভুয়া বা জাঙ্ক অর্ডার আগেই ফিল্টার হয়ে যায়।',
        ],
        'pixel_protection' => [
            'summary' => 'Purchase ইভেন্ট শুধু Confirmed অর্ডারে — ফেক অর্ডারে অ্যাড বাজেট নষ্ট হয় না।',
            'detail' => 'Meta Pixel + Conversions API সেটআপ করুন WooEasyLife থেকেই। Purchase কনভার্সন ট্রিগার হয় শুধু অর্ডার Confirmed হলে (আপনি চাইলে অন্য স্ট্যাটাসও দিতে পারেন), তাই ফেক বা পরে ক্যানসেল হওয়া COD অর্ডার পিক্সেলে যায় না। সার্ভার-সাইড CAPI ব্রাউজার ইভেন্টের সাথে ডিডুপ্লিকেটেড — iOS ও অ্যাড-ব্লকারেও ডাটা নির্ভুল থাকে, Facebook শুধু আসল বায়ার শেখে।',
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
            'summary' => 'ইনকামিং কলে কাস্টমার ও অর্ডার শনাক্ত।',
            'detail' => 'কাস্টমার কল করলে অ্যাপ/সিস্টেমে নাম, অর্ডার হিস্ট্রি দেখা যায় — স্টাফ দ্রুত রেসপন্স দিতে পারে, কলের সাথে অর্ডার লিংক হয়।',
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
            'summary' => 'অসম্পূর্ণ চেকআউট আলাদা লিস্টে ধরা পড়ে।',
            'detail' => 'কাস্টমার কার্টে প্রোডাক্ট রেখে বা চেকআউট শুরু করে অর্ডার প্লেস করেনি — এগুলো নির্দিষ্ট সময় পর মিসিং অর্ডার লিস্টে জমা হয়, নাম-নম্বর-কার্ট সহ।',
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
            'detail' => 'কাকে ফোন-কনফার্ম, কাকে সরাসরি শিপ — ডাটা দিয়ে।',
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
        'headline' => 'কুরিয়ার রেকর্ড — অর্ডার পাঠানোর আগেই দেখুন',
        'subtitle' => 'লক্ষ লক্ষ ডেলিভারি রেকর্ড যাচাই করা আছে। কাস্টমারের নম্বর দিয়ে আগেই বুঝে নিন — এই মানুষ আগে কতবার নিয়েছে, কতবার ফেরত দিয়েছে।',
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
        'headline' => 'রিটার্নেই প্রতি মাসে হাজার হাজার টাকা চলে যাচ্ছে?',
        'subtitle' => 'হাতে-কলমে সব সামলানো বনাম স্মার্ট টুল — মাস শেষে পকেটে কত থাকে?',
        'without' => [
            'title' => 'WooEasyLife ছাড়া',
            'items' => [
                'ফেক অর্ডার পাঠিয়ে রিটার্ন খরচ গোনা (মাসে ৳২০,০০০–৪৫,০০০+)',
                'প্রতিটা অর্ডার কুরিয়ার সাইটে হাতে এন্ট্রি (দিনে ৩+ ঘণ্টা)',
                'একই অর্ডার বারবার এসে প্যাকেজিং নষ্ট',
                'সাইট বদলে ঘোরা — সময় নষ্ট, অর্ডার মিস',
                'কার্টে রেখে চলে যাওয়া কাস্টমার — বিক্রি আর ফেরে না',
            ],
            'summary' => 'মাসে হাজার হাজার টাকা অকারণে খরচ',
        ],
        'with' => [
            'title' => 'WooEasyLife দিয়ে',
            'items' => [
                'পাঠানোর আগেই ফ্রড চেক — রিটার্ন কমে',
                'কনফার্ম হলেই অটো কুরিয়ার এন্ট্রি',
                'ঝুঁকিপূর্ণ/ডুপ্লিকেট অর্ডার আগেই আটকে',
                'নতুন অর্ডার এলেই ফোনে অ্যালার্ট',
                'সব ওয়েবসাইট এক জায়গায়',
                'হারানো অর্ডার ফেরান — অ্যাড খরচ নষ্ট হয় না',
            ],
            'summary' => 'ফেক কমে, সফল ডেলিভারি বাড়ে — লাভও বাড়ে',
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
