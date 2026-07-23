<?php

/** Long-form EN pillar — Facebook Page COD order management. */

return [
    [
        'heading' => 'Quick answer — how to run Facebook page COD in Bangladesh',
        'paragraphs' => [
            'Many Bangladesh sellers run Cash-on-Delivery through Facebook/Instagram pages—with or without WooCommerce. Losses cluster in the same places: leads lost in the inbox, fake numbers, customers who say “yes” then refuse delivery, and hours of manual courier panel copy-paste.',
            'Working order: capture Messenger/comment lead → check mobile on /en/bd-fraud-checker → apply green/yellow/red rules (/en/customer-verification) → confirm → WooCommerce record → book only verified orders with /en/courier-auto-entry (Pathao/Steadfast/RedX). Bangla hub: /facebook-page-cod-management. Full system: /en/woocommerce-bangladesh.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/omnichannel-inbox.jpg',
                'alt' => 'Facebook page and WooCommerce COD inbox workflow Bangladesh',
                'caption' => 'Page inbox → verify → confirm → store → courier — one chain',
            ],
        ],
    ],
    [
        'heading' => 'Why page COD feels different—but the risk is the same',
        'paragraphs' => [
            'Page orders often skip a checkout form, so sellers assume fraud tools are “for websites only.” In practice a large share of fake COD starts in Messenger or comments—prank numbers, competitor interference, or curious price-check leads who never receive the parcel.',
            'Even with WooCommerce, page leads are a second channel: the same buyer can order twice, two staff can book twice, and return loss doubles. Page and store should share fraud zones and blacklists—setup: /en/fake-order-protection.',
            'Measure monthly bleed with /en/return-loss-calculator. Page-only sellers use the same ৳ math—delivered COD, not order count.',
        ],
    ],
    [
        'heading' => 'End-to-end flow: Messenger → confirm → courier',
        'paragraphs' => [
            'Standard SOP in six steps. (1) Lead capture: name, 11-digit mobile, address/area, product/size, COD amount. (2) Fraud signal: /en/bd-fraud-checker or /en/fake-customer-check. (3) Zone action: green confirm fast; yellow call/OTP; red advance delivery fee or hold—details /en/customer-verification.',
            '(4) System record: one WooCommerce (or central dashboard) entry—not a screenshot chat log. (5) Courier: /en/courier-auto-entry for Pathao/Steadfast/RedX; courier guides: /en/pathao-courier-guide, /en/steadfast-integration, /en/redx-courier-guide. (6) Out-for-delivery alert: /en/woocommerce-notifications.',
            'Break any link and you lose money: auto-book without fraud filters → RTS; book without a record → broken accounts; skip notifications → “customer unavailable” returns.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/fraud-layers.jpg',
                'alt' => 'Facebook COD fraud check layers before courier booking',
                'caption' => 'Multi-layer on page leads too: check → OTP/call → confirm → book',
            ],
        ],
    ],
    [
        'heading' => 'Inbox order loss: screenshots and missing leads',
        'paragraphs' => [
            'The quietest page-seller loss: a customer asked the price, staff replied, and nothing was saved—next day the thread is gone in the scroll. Another pattern: incomplete address and “I’ll order later.”',
            'Fix: turn every serious lead into a draft order immediately (name+phone minimum). WooEasyLife missing/incomplete order recovery helps pull dropped checkouts and half-written addresses; AI text/image→order speeds field fill from chat screenshots.',
            'Team rule: “replied in inbox” is not done. Done = system status (new / call pending / confirmed / hold / booked). Change status on the go with /en/woocommerce-mobile-app.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/checkout-dropout.jpg',
                'alt' => 'Incomplete Facebook and WooCommerce order recovery',
                'caption' => 'Half-written leads and dropped checkouts — money vanishes without recovery',
            ],
        ],
    ],
    [
        'heading' => 'Common fake-order patterns from Facebook pages',
        'paragraphs' => [
            'Pattern A: same number orders across pages—polite chat, red history. Pattern B: fake number, call goes dead. Pattern C: low success-rate buyers bite on ad creative, refuse when the rider arrives. Pattern D: duplicates—same phone messages two staff.',
            'Answer each with tools + SOP. A/C: /en/bd-fraud-checker zones. B: call or OTP before confirm (/en/fake-order-protection). D: duplicate phone block and shared blacklist. Bangla deep guide also at /ki-vabe-fake-order-atkabo; English: /en/ki-vabe-fake-order-atkabo.',
            'Remember—fraud check is a signal, not autopilot. Do not blind-ship red zones; an advance delivery fee drops many pranks.',
        ],
    ],
    [
        'heading' => 'Fraud check before confirm — page SOP',
        'paragraphs' => [
            'Inbox template: when the customer sends product+address, staff checks the number first, then sends the confirm message. “Book now, check later” is hard to reverse once the parcel is moving.',
            'Green (high success): confirm and auto-enter if stock exists. Yellow: “calling / sending OTP” → then approve. Red: advance fee or cancel; write the reason in notes so another staffer does not ship anyway.',
            'Checker-only tools break down at 50+ page orders/day—compare /en/fraudbd-alternative. WooEasyLife combines check + blocks + booking on one platform.',
        ],
    ],
    [
        'heading' => 'OTP, duplicate blocks, and blacklist — page + store together',
        'paragraphs' => [
            'Even page-first sellers eventually add a landing or WooCommerce checkout; checkout OTP kills fake numbers early. For pure Messenger leads, manual OTP/call follows the same policy. Duplicate phone/IP rules cut double parcels.',
            'Share blacklists: a number that pranked your page should also fail store checkout. Setup: /en/fake-order-protection and /en/customer-verification.',
            'Daily order limits reduce burst attacks and bot-like patterns—especially on viral ad days.',
        ],
    ],
    [
        'heading' => 'WooCommerce sync — do not run the page as a second store',
        'paragraphs' => [
            'Two systems (Messenger notes + a separate spreadsheet) means double mistakes. Goal: page or web → one order ID, one status, one courier consignment.',
            'Keep SKU, variants, and inventory in WooCommerce; treat the page as acquisition. Confirmed page orders created in the store unlock packing lists, invoices, and stickers from one place.',
            'For multi-page/multi-store teams, use the common mobile dashboard—/en/woocommerce-mobile-app—so staff do not miss another page’s queue.',
        ],
    ],
    [
        'heading' => 'Courier auto entry — after page confirm only',
        'paragraphs' => [
            'Typing Pathao/Steadfast/RedX panels by hand after Messenger confirm wastes hours and creates wrong area codes. Use /en/courier-auto-entry for verified orders; let webhooks write tracking back to the order.',
            'Critical: do not enable auto-ship without fraud filters. Automation saves time—and can accelerate losses on bad orders. Daily flow: /en/courier-auto-entry; courier setup spokes linked above.',
            'When returns arrive, log the reason (fake / unavailable / no cash)—so the next page confirm marks the same number yellow/red. Playbook: /en/cod-return-reduction.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/courier-auto-entry.jpg',
                'alt' => 'Courier auto entry after Facebook page order confirm',
                'caption' => 'Auto entry after confirm — not copy-paste',
            ],
        ],
    ],
    [
        'heading' => 'Mobile ops: call ID, push, approve/hold',
        'paragraphs' => [
            'Page orders arrive day and night. If browser admin is closed, yellow-zone leads slip. The WooEasyLife app gives push alerts, call identifier, and courier movement notifications so you can approve/hold from the road.',
            'Split roles: who can confirm vs who can book—so new staff cannot ship red zones. Guide: /en/woocommerce-mobile-app.',
            'Send out-for-delivery SMS/WhatsApp to page buyers too—“rider coming today, keep COD ৳X ready”—high ROI against returns. Templates: /en/woocommerce-notifications.',
        ],
    ],
    [
        'heading' => 'Ad orders vs organic page orders — one rulebook',
        'paragraphs' => [
            'Facebook Ads orders and organic inbox orders may use different creatives, but fraud zones and courier SOP should be identical. Scaling ads also scales red-zone volume—stabilize filters first.',
            'Keep Pixel/CAPI clean: /en/facebook-ads-for-woocommerce. Reported vs delivery-adjusted ROAS: /en/ads-roas-calculator. Dirty inbox quality also poisons ad audiences.',
            'Creative should match real product photos and price—overclaiming in ads creates unrealistic inbox expectations and more RTS.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/cod-loss-math.jpg',
                'alt' => 'COD return loss math for Facebook page sellers Bangladesh',
                'caption' => 'Not page order count — delivered vs RTS net profit',
            ],
        ],
    ],
    [
        'heading' => '30-day action plan — page COD',
        'paragraphs' => [
            'Week 1: mandate /en/bd-fraud-checker before every confirm; baseline ৳ on /en/return-loss-calculator. Week 2: write yellow/red SOP and train staff; duplicate phone rules; shared blacklist.',
            'Week 3: 100% of confirmed orders recorded in WooCommerce; /en/courier-auto-entry live (verified only). Week 4: /en/woocommerce-notifications out-for-delivery; mobile approve/hold; weekly RTS% review (/en/cod-return-reduction).',
            'KPIs: inbox→confirm rate, confirm→delivered rate, RTS %, return ৳, staff hours per 50 orders. Do not raise ad budget until RTS is stable.',
        ],
    ],
    [
        'heading' => 'Checklist and related resources',
        'paragraphs' => [
            'Checklist: complete lead fields ✓ fraud check ✓ zone action ✓ system order ✓ verified-only auto entry ✓ out-for-delivery message ✓ weekly RTS review ✓.',
            'Tools and guides: /en/bd-fraud-checker, /en/fake-order-protection, /en/ki-vabe-fake-order-atkabo, /en/customer-verification, /en/courier-auto-entry, /en/woocommerce-mobile-app, /en/woocommerce-notifications, /en/cod-return-reduction, /en/return-loss-calculator, /en/facebook-ads-for-woocommerce, /en/fraudbd-alternative, /pricing.',
            'Bangla mirror: /facebook-page-cod-management. Master hub: /en/woocommerce-bangladesh. Check one number today and put the SOP live.',
        ],
    ],
];
