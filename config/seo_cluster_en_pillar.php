<?php

/**
 * English WooCommerce Bangladesh pillar — concise intros + shared diagram figures.
 */

return [
    [
        'heading' => 'Quick answer',
        'paragraphs' => [
            'Complete WooCommerce Bangladesh master guide (2026): 30 parts—from COD risk math to courier APIs, OTP, messaging, Meta CAPI, GA4, inventory, security, and scaling.',
            'Start free: /en/bd-fraud-checker → /en/fake-order-protection → /en/courier-auto-entry. Measure loss with /en/return-loss-calculator. Bangla: /woocommerce-bangladesh.',
        ],
    ],
    [
        'heading' => 'Introduction: Bangladesh e-commerce landscape',
        'paragraphs' => [
            'Bangladesh online retail moved from social-only selling into a data-driven, automation-heavy industry. Facebook/Instagram commerce still matters, but durable growth needs a systemized WooCommerce stack.',
            'In 2026 the market is COD-heavy (~80% prefer pay-on-delivery), mobile-first (90%+ traffic), and hurt by checkout dropout plus fake/refused parcels. This master guide covers prevention, courier automation, messaging, clean tracking, and scaling.',
        ],
    ],
    [
        'heading' => 'Part 1/30 — Landscape and goals',
        'paragraphs' => [
            'What you will learn: why local buyer behavior forces a different WooCommerce playbook than “global best practices.”',
            'WooCommerce wins for open-source flexibility and plugin depth. Success requires understanding COD preference, mobile-first traffic, high checkout dropout, and fake/refused parcels—the core pain this 30-part guide solves.',
        ],
    ],
    [
        'heading' => 'Part 2/30 — COD hidden risks and return math',
        'paragraphs' => [
            'What you will learn: how returns flip net profit even when top-line orders look healthy.',
            'COD grows sales but hides three risks: zero commitment, fake/prank orders, and delayed payouts that lock working capital.',
            'Model every order with COGS, ad CPA, packaging, delivery, and reverse fees. A few returns can erase the profit from many successful deliveries.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/cod-loss-math.jpg',
                'alt' => 'COD success vs return profit comparison diagram',
                'caption' => 'Successful deliveries vs returns — how net profit flips',
            ],
        ],
    ],
    [
        'heading' => 'Part 3/30 — Courier API + automated fraud detection',
        'paragraphs' => [
            'What you will learn: score risk from courier history before you confirm.',
            'Manual phone checks do not scale. Use courier history/success-rate signals to auto-flag risky numbers, then confirm fast on clean history and hold/call on weak patterns.',
            'Start free: /en/bd-fraud-checker.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/fraud-layers.jpg',
                'alt' => 'Multi-layer fraud prevention flow',
                'caption' => 'Fraud layers: history check → OTP → block → confirm',
            ],
        ],
    ],
    [
        'heading' => 'Part 4/30 — One-click fast checkout',
        'paragraphs' => [
            'What you will learn: why long mobile forms destroy conversion.',
            'Fewer fields, bigger tap targets, phone auto-format, and simple area mapping reduce incomplete checkouts on mobile-first traffic.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/checkout-dropout.jpg',
                'alt' => 'Mobile checkout dropout funnel',
                'caption' => 'Mobile checkout funnel — where customers drop',
            ],
        ],
    ],
    [
        'heading' => 'Part 5/30 — Incomplete order recovery',
        'paragraphs' => [
            'What you will learn: recover abandoned checkouts with timed SMS/WhatsApp sequences.',
            'Trigger within 5–15 minutes with a clear finish-order CTA. Keep sequences to 1–2 steps to avoid spam.',
        ],
    ],
    [
        'heading' => 'Part 6/30 — SMS and WhatsApp workflows',
        'paragraphs' => [
            'What you will learn: which channel for which message.',
            'SMS is fast and reliable for OTP/alerts; WhatsApp wins on open rate and rich media. Use both for confirmations, tracking, and recovery.',
        ],
    ],
    [
        'heading' => 'Part 7/30 — OTP verification',
        'paragraphs' => [
            'What you will learn: OTP plus blacklist as part of a layered defense.',
            'Require checkout OTP, block duplicates, and still use courier history + confirmation. Details: /en/fake-order-protection and /en/customer-verification.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/fraud-layers.jpg',
                'alt' => 'OTP and fraud prevention layers',
                'caption' => 'OTP inside a multi-layer fake-order defense',
            ],
        ],
    ],
    [
        'heading' => 'Part 8/30 — Admin mobile app',
        'paragraphs' => [
            'What you will learn: manage orders without a laptop.',
            'Push new orders, incomplete alerts, and courier status to mobile so approve/hold decisions happen faster. Guide: /en/woocommerce-mobile-app.',
        ],
    ],
    [
        'heading' => 'Part 9/30 — Steadfast and Pathao API setup',
        'paragraphs' => [
            'What you will learn: connect courier merchant APIs for auto booking.',
            'With keys, webhooks, and area/weight mapping set, confirmation creates parcels without copy-paste. See /en/steadfast-integration, /en/pathao-courier-guide, /en/redx-courier-guide.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/courier-auto-entry.jpg',
                'alt' => 'Courier auto entry pipeline',
                'caption' => 'Confirm → courier API → tracking ID sync',
            ],
        ],
    ],
    [
        'heading' => 'Part 10/30 — Parcel tracking notifications',
        'paragraphs' => [
            'What you will learn: push tracking updates to customers automatically.',
            'When courier webhooks update status, sync WooCommerce and notify via SMS/WhatsApp to cut “where is my order?” calls. Workflow: /en/courier-auto-entry.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/courier-auto-entry.jpg',
                'alt' => 'Automated parcel tracking notifications',
                'caption' => 'Tracking sync triggers customer notifications',
            ],
        ],
    ],
    [
        'heading' => 'Part 11/30 — Post-delivery reviews',
        'paragraphs' => [
            'What you will learn: request reviews 24–48h after delivery and route negatives to support first.',
        ],
    ],
    [
        'heading' => 'Part 12/30 — LTV and repeat purchase',
        'paragraphs' => [
            'What you will learn: schedule repurchase reminders from consumption cycles so LTV can support CAC.',
        ],
    ],
    [
        'heading' => 'Part 13/30 — VIP membership club',
        'paragraphs' => [
            'What you will learn: simple Silver/Gold/Platinum tiers with clear perks beat complex loyalty schemes.',
        ],
    ],
    [
        'heading' => 'Part 14/30 — Meta Pixel and CAPI',
        'paragraphs' => [
            'What you will learn: why browser Pixel alone under-reports and how CAPI restores accuracy.',
            'Best practice is redundant Browser Pixel + server Conversions API. Deep dive: /en/facebook-ads-for-woocommerce.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/pixel-vs-capi.jpg',
                'alt' => 'Browser Pixel vs Server CAPI data paths',
                'caption' => 'Browser Pixel (high data loss) vs Server CAPI (higher accuracy)',
            ],
        ],
    ],
    [
        'heading' => 'Part 15/30 — GA4 server-side',
        'paragraphs' => [
            'What you will learn: map WooCommerce actions to GA4 ecommerce events with clean parameters.',
        ],
    ],
    [
        'heading' => 'Part 16/30 — Google Shopping and PMax',
        'paragraphs' => [
            'What you will learn: clean product feeds and filter fake/returned purchases from conversion signals.',
        ],
    ],
    [
        'heading' => 'Part 17/30 — Inventory automation',
        'paragraphs' => [
            'What you will learn: low-stock alerts and pause ads when SKUs sell out to stop wasted spend.',
        ],
    ],
    [
        'heading' => 'Part 18/30 — WhatsApp+SMS combo engine',
        'paragraphs' => [
            'What you will learn: route order events through one rules engine across WhatsApp, SMS, email, and admin alerts.',
            'More: /en/woocommerce-notifications.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/omnichannel-inbox.jpg',
                'alt' => 'Central omnichannel messaging dashboard',
                'caption' => 'Central dashboard routing WhatsApp, SMS, Email, and admin alerts',
            ],
        ],
    ],
    [
        'heading' => 'Part 19/30 — Messenger/Instagram DM funnels',
        'paragraphs' => [
            'What you will learn: auto-answer FAQs in DMs and hand hot leads to humans with checkout links.',
        ],
    ],
    [
        'heading' => 'Part 20/30 — Email and cart abandonment',
        'paragraphs' => [
            'What you will learn: a short 2–3 email recovery sequence with SMS/WhatsApp fallback.',
        ],
    ],
    [
        'heading' => 'Part 21/30 — Google Ads retargeting and SEO automation',
        'paragraphs' => [
            'What you will learn: retarget viewers who did not purchase and strengthen on-page SEO with this hub’s spoke links.',
        ],
    ],
    [
        'heading' => 'Part 22/30 — A/B testing and CRO',
        'paragraphs' => [
            'What you will learn: test one variable at a time and chase mobile load speed near one second.',
        ],
    ],
    [
        'heading' => 'Part 23/30 — AI support and voice agents',
        'paragraphs' => [
            'What you will learn: bots for status/FAQ; humans for complaints—never let bots invent stock or ETAs.',
        ],
    ],
    [
        'heading' => 'Part 24/30 — CRM and RFM',
        'paragraphs' => [
            'What you will learn: segment VIP, at-risk, and one-time buyers so promos match behavior.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/rfm-segments.jpg',
                'alt' => 'RFM customer segments VIP at-risk one-time',
                'caption' => 'RFM segments: VIP, At-Risk, and One-Time Buyers',
            ],
        ],
    ],
    [
        'heading' => 'Part 25/30 — Courier returns and cancellation prevention',
        'paragraphs' => [
            'What you will learn: confirm before ship, validate addresses, and blacklist repeat refusers. Playbook: /en/cod-return-reduction.',
        ],
    ],
    [
        'heading' => 'Part 26/30 — Multi-vendor inventory',
        'paragraphs' => [
            'What you will learn: one inventory source of truth across channels so fraud and courier rules stay consistent.',
        ],
    ],
    [
        'heading' => 'Part 27/30 — Profit ledger and tax',
        'paragraphs' => [
            'What you will learn: track net profit per order after COGS, ads, gateway, courier, and RTS allowance—not gross sales alone.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/cod-loss-math.jpg',
                'alt' => 'Net profit ledger cost components',
                'caption' => 'Net profit = selling price − (COGS + ads + courier + gateway + RTS)',
            ],
        ],
    ],
    [
        'heading' => 'Part 28/30 — Security and compliance',
        'paragraphs' => [
            'What you will learn: WAF/rate limits, hardened admin access, and offsite encrypted backups.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/security-waf.jpg',
                'alt' => 'Cloudflare WAF allow vs block flow',
                'caption' => 'Incoming traffic → WAF → real customer or auto-block',
            ],
        ],
    ],
    [
        'heading' => 'Part 29/30 — KPI dashboard (CAC/LTV/ROAS)',
        'paragraphs' => [
            'What you will learn: compute CAC and LTV, aim for LTV:CAC around 3:1+, and pipe Woo + Ads + courier data into one live dashboard.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/etl-dashboard.jpg',
                'alt' => 'ETL pipeline into live analytics dashboard',
                'caption' => 'Woo + Ads + Courier data → ETL → live KPI dashboard',
            ],
        ],
    ],
    [
        'heading' => 'Part 30/30 — Scaling roadmap and future-proofing',
        'paragraphs' => [
            'What you will learn: quarterly audits across API reliability, database health, security/WAF, and financial reconciliation.',
            'Foundation → full automation → hyper-scale. Privacy-safe tracking and ops automation remain the 2026+ edge.',
        ],
        'figures' => [
            [
                'src' => '/images/seo/cluster/audit-checklist.jpg',
                'alt' => 'Quarterly WooCommerce system audit checklist',
                'caption' => 'Quarterly audit: API, database, security, finance — four layers',
            ],
        ],
    ],
];
