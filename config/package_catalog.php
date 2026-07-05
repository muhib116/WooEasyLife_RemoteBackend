<?php

return [
    'durations' => [
        'free_trial',
        '1_month',
        '5_month',
        '1_year',
    ],

    /*
    |--------------------------------------------------------------------------
    | Power Full Features (stored on packages & subscriptions)
    |--------------------------------------------------------------------------
    |
    | English keys are persisted in JSON. Bangla labels are for admin/pricing UI.
    |
    */
    'power_feature_keys' => [
        'create_order',
        'order_cloning',
        'call_and_status_log',
        'ai_intelligence',
        'app_connect',
        'app_store_limit',
        'courier_automation',
        'custom_status_management',
        'customer_blacklist',
        'employee_management',
        'fake_order_protection',
        'fraud_customer_checker',
        'customer_delivery_history',
        'customer_behavior',
        'label_and_pos_sticker_print',
        'missing_orders',
        'sms_management',
        'pixel_protection',
    ],

    'power_feature_labels_bn' => [
        'create_order' => 'কাস্টম অর্ডার তৈরি',
        'order_cloning' => 'অর্ডার ক্লোনিং',
        'call_and_status_log' => 'কল ও স্ট্যাটাস লগ',
        'ai_intelligence' => 'এআই ইন্টেলিজেন্স',
        'app_connect' => 'অ্যাপ কানেক্ট',
        'app_store_limit' => 'অ্যাপ স্টোর লিমিট',
        'courier_automation' => 'কুরিয়ার অটোমেশন',
        'custom_status_management' => 'কাস্টম স্ট্যাটাস ম্যানেজমেন্ট',
        'customer_blacklist' => 'কাস্টমার ব্ল্যাকলিস্ট',
        'employee_management' => 'এমপ্লয়ী ম্যানেজমেন্ট',
        'fake_order_protection' => 'ফেক অর্ডার প্রোটেকশন',
        'fraud_customer_checker' => 'ফ্রড কাস্টমার চেকার',
        'customer_delivery_history' => 'কাস্টমার ডেলিভারি হিস্ট্রি',
        'customer_behavior' => 'কাস্টমার বিহেভিয়ার',
        'label_and_pos_sticker_print' => 'লেবেল ও POS স্টিকার প্রিন্ট',
        'missing_orders' => 'মিসিং অর্ডার',
        'sms_management' => 'এসএমএস ম্যানেজমেন্ট',
        'pixel_protection' => 'পিক্সেল প্রোটেকশন',
    ],

    'power_feature_labels_en' => [
        'create_order' => 'Custom order create',
        'order_cloning' => 'Order cloning',
        'call_and_status_log' => 'Call and status log',
        'ai_intelligence' => 'AI intelligence',
        'app_connect' => 'WooEasyLife App Connect',
        'app_store_limit' => 'Multi-store support',
        'courier_automation' => 'Courier automation',
        'custom_status_management' => 'Custom status management',
        'customer_blacklist' => 'Customer black list',
        'employee_management' => 'Employee management',
        'fake_order_protection' => 'Fake order protection',
        'fraud_customer_checker' => 'Fraud customer checker',
        'customer_delivery_history' => 'Customer delivery history',
        'customer_behavior' => 'Customer behavior',
        'label_and_pos_sticker_print' => 'Label & POS sticker print',
        'missing_orders' => 'Missing orders',
        'sms_management' => 'SMS management',
        'pixel_protection' => 'Pixel protection',
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy granular keys (plugin API & landing page compatibility)
    |--------------------------------------------------------------------------
    */
    'plugin_feature_keys' => [
        'fraud_customer_checker',
        'three_courier_partner_integration',
        'courier_entry_automation',
        'customer_delivery_history',
        'customer_sms_for_order',
        'bulk_sms',
        'ai_text_order_create',
        'ai_image_to_order_create',
        'ai_incomplete_address_autocomplete',
        'ai_driven_customer_scoring',
        'daily_order_limit',
        'checkout_form_validation',
        'duplicate_order_validation',
        'checkout_otp_validation',
        'ip_block',
        'phone_email_block',
        'device_block',
        'bd_ip_restriction',
        'store_api_checkout_protection',
        'custom_status_manage',
        'customer_blacklist',
        'marketing_tools',
        'database_migration',
        'missing_orders',
        'missing_order_one_click_create',
        'pos_sticker_print',
        'invoice_print',
        'order_cloning',
        'customer_order_create',
        'customer_behavior_track',
        'repeat_customer_identifier',
        'order_source_identifier',
        'inline_shipping_change',
        'order_note_management',
        'cod_change',
        'ordered_product_management',
        'order_edit_product_variation',
        'quick_action_tool',
        'courier_auto_status_sync',
        'courier_webhook_integrations',
        'pixel_protection',
    ],

    'app_feature_keys' => [
        'one_click_app_connect',
        'multistore_order_notifications',
        'customer_call_identifier',
        'cross_store_order_detection',
        'call_history_with_duration',
        'common_dashboard',
        'courier_movement_notification',
        'notification_sound_management',
        'centralized_notifications',
        'admin_employee_manage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Power key → legacy keys (for plugin API expansion)
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Plugin subscription UI copy (Bangla — served via /api/package/plans)
    |--------------------------------------------------------------------------
    */
    'plugin_display' => [
        'features_heading_bn' => 'প্ল্যান ফিচার',
        'app_connect_label_bn' => 'মোবাইল অ্যাপ অন্তর্ভুক্ত',
        'badge_free_trial_bn' => 'বিনামূল্যে শুরু',
        'badge_special_bn' => 'সবচেয়ে জনপ্রিয়',
        'duration_free_trial_bn' => ':days দিন ফ্রি ট্রায়াল',
        'duration_1_month_bn' => 'মাসিক প্ল্যান',
        'duration_5_month_bn' => '৫ মাসের প্ল্যান',
        'duration_1_year_bn' => 'বার্ষিক প্ল্যান',
        'duration_default_bn' => 'প্ল্যান',
        'website_unlimited_bn' => 'আনলিমিটেড ওয়েবসাইট',
        'legacy_per_order_rate_bn' => 'অর্ডার প্রতি :rate টাকা',
        'more_features_label_bn' => '+ আরও :count ফিচার',
        'submission_title_bn' => 'পেমেন্ট সফলভাবে জমা হয়েছে',
        'submission_message_bn' => 'আপনার পেমেন্ট এডমিন যাচাইয়ের অপেক্ষায় আছে। অনুমোদন হওয়া পর্যন্ত বর্তমান সাবস্ক্রিপশন সক্রিয় থাকবে।',
        'submission_step_submitted_bn' => 'পেমেন্ট জমা হয়েছে',
        'submission_step_waiting_bn' => 'যাচাইয়ের অপেক্ষায়',
        'submission_step_waiting_detail_bn' => 'আমাদের টিম শীঘ্রই আপনার ট্রানজেকশন যাচাই করবে।',
        'submission_step_activation_bn' => 'প্ল্যান সক্রিয়করণ',
        'submission_step_activation_detail_bn' => 'অনুমোদনের পর প্ল্যান স্বয়ংক্রিয়ভাবে আপডেট হবে।',
        'submission_step_detail_bn' => ':plan — :amount টাকা (ট্রানজেকশন: :txn)',
        'submission_selected_plan_bn' => 'নির্বাচিত প্ল্যান',
        'billing_alerts_bn' => [
            'quota_exhausted' => 'আপনার অর্ডার কোটা শেষ। সাবস্ক্রিপশন নবায়নের জন্য পেমেন্ট জমা দিন।',
            'quota_critical' => 'মাত্র :countটি অর্ডার বাকি। সেবা ব্যাহত এড়াতে শীঘ্রই নবায়ন করুন।',
            'quota_low' => 'আপনার অবশিষ্ট অর্ডার কোটা কম (:countটি বাকি)।',
            'subscription_expired' => 'আপনার সাবস্ক্রিপশন প্ল্যানের মেয়াদ শেষ হয়েছে।',
            'subscription_expiring_today' => 'আপনার সাবস্ক্রিপশন প্ল্যান আজ মেয়াদ শেষ হচ্ছে।',
            'subscription_expiring_days' => 'আপনার সাবস্ক্রিপশন প্ল্যান :days দিনে মেয়াদ শেষ হবে।',
            'license_expired' => 'আপনার লাইসেন্স কী-এর মেয়াদ শেষ হয়েছে।',
            'license_expiring_today' => 'আপনার লাইসেন্স কী আজ মেয়াদ শেষ হচ্ছে।',
            'license_expiring_days' => 'আপনার লাইসেন্স কী :days দিনে মেয়াদ শেষ হবে।',
            'payment_pending' => 'আপনার পেমেন্ট অনুরোধ এডমিন যাচাইয়ের অপেক্ষায় আছে:detail। অন্য পেমেন্ট জমা দেওয়ার আগে যাচাইয়ের জন্য অপেক্ষা করুন।',
            'payment_pending_plan_fallback' => 'নির্বাচিত প্ল্যান',
            'sms_low' => 'আপনার এসএমএস ব্যালেন্স ২০ টাকার কম।',
        ],
        'pricing_notes_bn' => [
            'upgrade' => 'সম্পূর্ণ প্ল্যান মূল্য প্রযোজ্য। অব্যবহৃত কোটা ও বাকি সাবস্ক্রিপশন সময় আপগ্রেডে ক্রেডিট হিসেবে যায় না।',
            'upgrade_remaining' => 'সম্পূর্ণ প্ল্যান মূল্য প্রযোজ্য। অব্যবহৃত কোটা ও বাকি সাবস্ক্রিপশন সময় আপগ্রেডে ক্রেডিট হিসেবে যায় না। বর্তমানে :countটি অব্যবহৃত অর্ডার বাকি আছে।',
            'downgrade' => 'পেমেন্ট অনুমোদনের পর ডাউনগ্রেড কার্যকর হবে। অব্যবহৃত কোটা ও বাকি সময় ক্রেডিট হিসেবে যায় না।',
            'renew' => 'নবায়নে বর্তমান কোটার পরিবর্তে ক্রয়কৃত পরিমাণ প্রযোজ্য হবে।',
            'subscribe' => 'প্রথম সাবস্ক্রিপশন। পেমেন্ট অনুমোদনের পর প্ল্যান সক্রিয় হবে।',
            'default' => 'পেমেন্ট অনুমোদনের পর প্ল্যান সক্রিয় হবে।',
            'free_trial_used' => 'এই স্টোরের ফ্রি ট্রায়াল ইতিমধ্যে ব্যবহার করা হয়েছে।',
        ],
    ],

    'power_to_legacy' => [
        'create_order' => [
            'customer_order_create',
        ],
        'order_cloning' => [
            'order_cloning',
        ],
        'call_and_status_log' => [
            'call_history_with_duration',
            'customer_call_identifier',
            'order_note_management',
            'order_source_identifier',
        ],
        'ai_intelligence' => [
            'ai_text_order_create',
            'ai_image_to_order_create',
            'ai_incomplete_address_autocomplete',
            'ai_driven_customer_scoring',
        ],
        'app_connect' => [
            'one_click_app_connect',
            'multistore_order_notifications',
            'cross_store_order_detection',
            'common_dashboard',
            'courier_movement_notification',
            'notification_sound_management',
            'centralized_notifications',
        ],
        'app_store_limit' => [
            'multistore_order_notifications',
            'cross_store_order_detection',
            'common_dashboard',
            'centralized_notifications',
        ],
        'courier_automation' => [
            'three_courier_partner_integration',
            'courier_entry_automation',
            'courier_auto_status_sync',
            'courier_webhook_integrations',
        ],
        'custom_status_management' => [
            'custom_status_manage',
        ],
        'customer_blacklist' => [
            'customer_blacklist',
        ],
        'employee_management' => [
            'admin_employee_manage',
        ],
        'fake_order_protection' => [
            'duplicate_order_validation',
            'checkout_form_validation',
            'checkout_otp_validation',
            'daily_order_limit',
            'store_api_checkout_protection',
            'ip_block',
            'phone_email_block',
            'device_block',
            'bd_ip_restriction',
        ],
        'fraud_customer_checker' => [
            'fraud_customer_checker',
        ],
        'customer_delivery_history' => [
            'customer_delivery_history',
        ],
        'customer_behavior' => [
            'customer_behavior_track',
            'repeat_customer_identifier',
        ],
        'label_and_pos_sticker_print' => [
            'pos_sticker_print',
            'invoice_print',
        ],
        'missing_orders' => [
            'missing_orders',
            'missing_order_one_click_create',
        ],
        'sms_management' => [
            'customer_sms_for_order',
            'bulk_sms',
        ],
        'pixel_protection' => [
            'pixel_protection',
        ],
    ],
];
