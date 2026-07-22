<?php

/**
 * English overlays for the marketing home (/en → Welcome3).
 * Merged by LandingPageService when locale is "en".
 */
return [
    'hero' => [
        'badge' => 'For WooCommerce stores · BD Fraud Checker',
        'headline' => 'Losing thousands of taka every month to fake orders?',
        'headline_accent' => 'Verify courier history with the fraud checker before you confirm.',
        'subheadline' => 'Auto-enter confirmed orders into the courier, recover abandoned carts, and cut monthly losses — built for Bangladesh COD sellers.',
    ],

    'hero_trust_badges' => [
        '14-day free trial',
        'payment_methods',
        'WhatsApp support',
    ],

    'hero_bullets' => [
        'Stopping just 10 fake orders/day can cut ~৳45,000+ monthly return loss',
        'No more hopping courier panels — confirm triggers auto-entry and can save 3+ hours/day',
        'Bring back cart abandoners and sell more without raising ad spend',
    ],

    'how_it_works' => [
        [
            'step' => '01',
            'title' => 'Start a free trial',
            'description' => 'Use it free for 14 days — WhatsApp help for full setup.',
        ],
        [
            'step' => '02',
            'title' => 'Connect your site',
            'description' => 'Link WooEasyLife to WooCommerce — ready in minutes.',
        ],
        [
            'step' => '03',
            'title' => 'Less loss, less hassle',
            'description' => 'Turn on customer checks and auto courier entry to cut returns and save time.',
        ],
        [
            'step' => '04',
            'title' => 'Every site in one mobile app',
            'description' => 'See orders and updates across stores without switching dashboards.',
        ],
    ],

    'app_showcase' => [
        'headline' => 'Your whole business in your pocket',
        'subheadline' => 'Phone alerts on new orders. Missing orders in one place. Never miss a sale when you are out.',
        'screenshot' => '/images/woo-easy-life/hub.jpg',
        'screenshot_alt' => 'WooEasyLife mobile app — home screen',
        'screenshots' => [
            [
                'src' => '/images/woo-easy-life/hub.jpg',
                'alt' => 'WooEasyLife app home — Dashboard, Orders, Missing, Fraud',
                'label' => 'Home',
            ],
            [
                'src' => '/images/woo-easy-life/dashboard.jpg',
                'alt' => 'WooEasyLife dashboard — orders and COD summary',
                'label' => 'Dashboard',
            ],
            [
                'src' => '/images/woo-easy-life/orders.jpg',
                'alt' => 'WooEasyLife order list — abandoned orders and call button',
                'label' => 'Orders',
            ],
            [
                'src' => '/images/woo-easy-life/common-dashboard.jpg',
                'alt' => 'WooEasyLife common dashboard — multi-store at a glance',
                'label' => 'Multi-store',
            ],
            [
                'src' => '/images/woo-easy-life/menu-nav.jpg',
                'alt' => 'WooEasyLife menu — Missing Orders and New order badges',
                'label' => 'Menu',
            ],
        ],
        'benefits' => [
            'Order counts across every website at a glance',
            'Find missing orders and recover sales',
            'Phone alerts when new orders arrive',
            'Staff and multiple sites — one app',
        ],
        'download_count' => '5,000+',
        'rating' => '4.8',
        'rating_count' => '320+',
    ],

    'fraud_benefit_cards' => [
        'headline' => 'Check courier history with the fraud checker before you confirm.',
        'subtitle' => 'Once a parcel ships, return cost is on you. Decide early from courier records.',
        'cards' => [
            [
                'icon' => '📊',
                'title' => 'Delivery and return history',
                'description' => 'See delivery and return records across supported couriers in one place.',
            ],
            [
                'icon' => '✅',
                'title' => 'Past order record',
                'description' => 'How many orders on this number succeeded vs returned — all in one view.',
            ],
            [
                'icon' => '🛡️',
                'title' => 'Lower fake-order risk',
                'description' => 'Spot risky parcels from low delivery rates or warning signals.',
            ],
            [
                'icon' => '📱',
                'title' => 'Free courier history checks',
                'description' => 'Check by number without registration — limited free daily searches.',
                'cta' => '#fraud-check',
                'cta_label' => 'Try free fraud check',
            ],
        ],
    ],

    'case_studies' => [
        'badge' => 'Seller stories',
        'headline' => 'Cut return loss and save thousands monthly',
        'subtitle' => 'Anonymized real-world style examples after fraud checks and fake-order protection.',
        'items' => [
            [
                'name' => 'Fashion store · Dhaka',
                'role' => '~25–30 COD orders/day',
                'savings' => '৳38,000+',
                'period' => 'Monthly savings',
                'quote' => 'We used to ship 8–10 fake/return parcels a day. After number checks and OTP, returns roughly halved. Courier fees alone save about ৳38k/month.',
            ],
            [
                'name' => 'Gadget shop · Chattogram',
                'role' => 'Facebook ads + COD',
                'savings' => '৳52,000+',
                'period' => 'Monthly savings',
                'quote' => 'Low-quality ad leads used to become parcels. We confirm after courier history — packaging and returns together save over ৳50k/month.',
            ],
            [
                'name' => 'Home care brand · Sylhet',
                'role' => 'Pathao + Steadfast',
                'savings' => '৳27,500+',
                'period' => 'Monthly savings',
                'quote' => 'Manual courier entry errors and fake orders both dropped. Auto-entry plus fraud checks cut about ৳28k/month in losses.',
            ],
        ],
    ],

    'stats' => [
        ['value' => '৳45,000+', 'label' => 'Example monthly return-loss savings'],
        ['value' => '3+ hrs', 'label' => 'Courier time saved per day'],
        ['value' => '5 couriers', 'label' => 'Pathao · Steadfast · RedX and more'],
        ['value' => '14 days', 'label' => 'Free trial — no card required'],
    ],

    'courier_performance' => [
        'badge' => 'Our database',
        'headline' => 'See courier history before you ship',
        'subtitle' => 'From millions of courier delivery records, look up a customer number instantly — how many deliveries succeeded vs returned.',
        'note' => '* Data updates regularly; figures vary by courier and time.',
        'kpis' => [
            ['label' => 'Parcels checked', 'value' => '12M+', 'tone' => 'neutral', 'icon' => 'box'],
            ['label' => 'Successful deliveries', 'value' => '11M+', 'tone' => 'safe', 'icon' => 'check'],
            ['label' => 'Cancel / return', 'value' => '1M+', 'tone' => 'risky', 'icon' => 'x'],
            ['label' => 'Avg success rate', 'value' => '91.5%', 'tone' => 'accent', 'icon' => 'trend'],
        ],
        'couriers' => [
            ['name' => 'Pathao', 'logo' => '/images/pathao.svg', 'delivered' => '5.4M', 'returned' => '410K', 'success_rate' => 93],
            ['name' => 'Steadfast', 'logo' => '/images/steadfast.svg', 'delivered' => '4.1M', 'returned' => '390K', 'success_rate' => 91],
            ['name' => 'RedX', 'logo' => '/images/redx.svg', 'delivered' => '1.7M', 'returned' => '210K', 'success_rate' => 88],
            ['name' => 'Carrybee', 'logo' => '/images/carrybee.svg', 'delivered' => '830K', 'returned' => '120K', 'success_rate' => 87],
            ['name' => 'Paperfly', 'logo' => '/images/paperfly.png', 'delivered' => '570K', 'returned' => '110K', 'success_rate' => 81],
        ],
    ],

    'loss_comparison' => [
        'headline' => 'Are returns eating thousands of taka every month?',
        'subtitle' => 'Manual work vs smart automation — what does month-end look like?',
        'without' => [
            'title' => 'Without WooEasyLife',
            'items' => [
                'Fake-order returns cost ৳20,000–45,000+/month',
                'Hand-entering every courier parcel wastes 3+ hours/day',
                'Duplicate orders burn packaging and time',
                'Multi-site chaos makes order management hard',
                'Most cart abandoners never come back',
            ],
            'summary' => 'Thousands of taka wasted every month',
        ],
        'with' => [
            'title' => 'With WooEasyLife',
            'items' => [
                'Ship after courier history — fewer returns',
                'One-click confirm with instant courier entry',
                'Catch risky or duplicate orders early',
                'Phone notifications as soon as orders arrive',
                'Control every website from one place',
                'Recover cart abandoners — protect ad spend',
            ],
            'summary' => 'Fewer fake orders, more successful deliveries — more profit',
        ],
    ],

    'enterprise_cta' => [
        'title' => 'Large team or multiple brands?',
        'subtitle' => 'More websites, custom limits, and dedicated support — we tailor a plan to your business.',
        'button_label' => 'Talk on WhatsApp',
    ],

    'integrations' => [
        'badge' => 'Works with your stack',
        'headline' => 'Fits the tools your business already uses',
        'subtitle' => 'Website to courier, SMS, and mobile app — in one place.',
        'items' => [
            ['icon' => 'woocommerce', 'title' => 'WooCommerce website', 'description' => 'Connect your site — orders sync automatically.', 'accent' => 'fuchsia'],
            ['icon' => 'courier', 'title' => 'Pathao · Steadfast · RedX', 'description' => 'Confirm triggers auto courier entry and updates.', 'accent' => 'amber'],
            ['icon' => 'sms', 'title' => 'SMS', 'description' => 'Order and delivery updates straight to the customer phone.', 'accent' => 'emerald'],
            ['icon' => 'app', 'title' => 'Mobile app (Android)', 'description' => 'Every site in one app — alerts and calling.', 'accent' => 'sky'],
            ['icon' => 'ai', 'title' => 'Message to order', 'description' => 'Create orders quickly from text or images.', 'accent' => 'violet'],
            ['icon' => 'api', 'title' => 'bKash · Nagad · Rocket', 'description' => 'Simple payments — subscriptions activate fast.', 'accent' => 'rose'],
        ],
    ],

    'fraud_check_demo' => [
        'phone_masked' => '017********',
        'risk_label' => 'Safe customer',
        'risk_tone' => 'safe',
        'total_order' => 42,
        'confirmed' => 38,
        'cancel' => 4,
        'success_rate' => '90%',
        'couriers' => [
            ['title' => 'Steadfast', 'confirmed' => 15, 'cancel' => 1, 'success_rate' => '94%'],
            ['title' => 'Pathao', 'confirmed' => 10, 'cancel' => 1, 'success_rate' => '91%'],
            ['title' => 'RedX', 'confirmed' => 6, 'cancel' => 1, 'success_rate' => '86%'],
            ['title' => 'Paperfly', 'confirmed' => 4, 'cancel' => 1, 'success_rate' => '80%'],
            ['title' => 'Carrybee', 'confirmed' => 3, 'cancel' => 0, 'success_rate' => '100%'],
        ],
    ],
];
