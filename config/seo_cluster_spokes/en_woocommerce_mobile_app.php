<?php

return [
    [
        'heading' => 'What is the WooEasyLife mobile app?',
        'paragraphs' => [
            'WooEasyLife is an Android app for WooCommerce merchants—not a shopping app for customers. If you run WooCommerce or Facebook COD in Bangladesh, you can see new orders on your phone, call the customer, check courier history risk hints, and confirm or hold the order without sitting at a laptop.',
            'You can connect more than one website with a QR scan. New orders can notify your phone. When a customer calls, the app can often match the call to an order. You can also start Pathao, SteadFast, and RedX parcel work from mobile. iPhone is not the main version yet.',
            'Author: Muhibbullah Ansary — see /en/about. To start, visit /pricing. Bangla guide: /woocommerce-mobile-app. Full system hub: /en/woocommerce-bangladesh.',
        ],
    ],
    [
        'heading' => 'Phone browser admin vs this app',
        'paragraphs' => [
            'Opening WooCommerce admin in Chrome on a phone is often slow, needs many taps, and new-order alerts may depend on email or manual refresh. Copying a number to call is also annoying.',
            'With the WooEasyLife app, new orders can arrive with sound. One tap can call or open WhatsApp. Risk hints can show on the order card. After confirm, courier entry is easier—so fewer orders are missed at a supplier visit or on the road.',
            'Simple compare: browser alerts are often late; the app is faster. Browser needs number copy; the app is one-tap call. Browser may need a separate fraud tool; the app can show history with the order.',
        ],
    ],
    [
        'heading' => 'Alerts for new and unfinished checkouts',
        'paragraphs' => [
            'When a customer places an order, the app should notify you—waiting only on email delays packing and delivery. In COD, delay can lose the sale to another page.',
            'If someone enters a phone number but leaves checkout unfinished, the app can alert you so you can call or message and recover the order. Messaging guide: /en/woocommerce-notifications.',
            'Turning every alert on can feel noisy. Prioritise new COD and unfinished checkouts first; mute or schedule nights so staff do not burn out.',
        ],
    ],
    [
        'heading' => 'One-tap call and WhatsApp',
        'paragraphs' => [
            'Open the order and tap Call—no copy-paste. WhatsApp shortcuts can send a short confirmation with order id, product, amount, and area.',
            'For medium risk or new customers, call or message first, then confirm. For strong history, confirm faster and book with /en/courier-auto-entry. For weak history, ask for advance fee or hold—see /en/customer-verification and /en/fake-order-protection.',
            'Note who called and what was said on the order so another teammate does not call the same person again.',
        ],
    ],
    [
        'heading' => 'History hints in the app: green, yellow, red',
        'paragraphs' => [
            'When you open an order you may see courier success rate or warnings. Green (strong delivery history): confirm faster. Yellow: call with care. Red (many returns / low success): consider advance fee or hold.',
            'Without this on mobile, teams often ship every order—and return costs rise. Free check: /en/bd-fraud-checker. Monthly loss math: /en/return-loss-calculator.',
            'Remember: the app shows a hint; it does not guarantee fake or genuine. You decide. More context: /en/woocommerce-bangladesh and /en/cod-return-reduction.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/fraud-layers.jpg',
                'alt' => 'Fraud hints and confirm steps on mobile',
                'caption' => 'Notification → read history → call/OTP → confirm → courier entry',
            ],
        ],
    ],
    [
        'heading' => 'Courier entry from mobile (Pathao / SteadFast / RedX)',
        'paragraphs' => [
            'After confirm, you can start courier entry from the app or plugin—less manual typing in the merchant panel. Setup: /en/pathao-courier-guide, /en/steadfast-integration, /en/redx-courier-guide. Daily flow: /en/courier-auto-entry.',
            'Test with one order first. Check the connection before sending many at once. When tracking is saved, update the customer via /en/woocommerce-notifications.',
            'Packing can still happen in the warehouse. The app’s job is fast decisions and starting entry. Match area and COD amount, then send.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/courier-auto-entry.jpg',
                'alt' => 'Courier entry after confirm',
                'caption' => 'Confirm on mobile → create parcel → save tracking on the order',
            ],
        ],
    ],
    [
        'heading' => 'Safe daily checklist',
        'paragraphs' => [
            '1) Keep alerts on for new and unfinished orders. 2) Open the order and read history (/en/bd-fraud-checker). 3) If unsure, call or use OTP (/en/customer-verification, /en/fake-order-protection). 4) Confirm. 5) Courier entry (/en/courier-auto-entry). 6) Send tracking (/en/woocommerce-notifications).',
            'Do not: ship blindly on bad history; hand off without a note; bulk-send before a connection test. If returns rise, measure with /en/return-loss-calculator.',
            'Split roles: who can confirm vs who only calls—fewer wrong bookings.',
        ],
    ],
    [
        'heading' => 'How to start',
        'paragraphs' => [
            'Start a WooEasyLife trial or plan on /pricing. Connect WooCommerce and couriers. Turn on fake-order protection (/en/fake-order-protection). Show Connect App QR from web or plugin → scan in the app → allow phone notifications.',
            'Run one test order: notification → call → confirm → courier entry. If notifications fail, check phone notification settings. If call fails, check the number format (01XXXXXXXXX).',
            'Read next: /en/woocommerce-bangladesh, /en/courier-auto-entry, /en/woocommerce-notifications. Bangla: /woocommerce-mobile-app.',
        ],
    ],
];
