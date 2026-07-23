<?php

/**
 * Overwrite English cluster guide sections with full 30-part coverage.
 * Run: php scripts/build_en_cluster_content.php
 */

$enParts = [
    0 => ['Introduction: Bangladesh e-commerce landscape', [
        'Bangladesh online retail has moved from social-only selling into a data-driven, automation-heavy industry. Facebook/Instagram commerce still matters, but durable brands increasingly run WordPress + WooCommerce for ownership, plugins, and customization.',
        'In 2026 the market is COD-heavy (about 80% prefer pay-on-delivery), mobile-first (90%+ traffic), and hurt by checkout dropout plus fake/refused parcels. This master guide shows how to systemize a WooCommerce store—from fraud filters to courier APIs, messaging, ads tracking, and scaling.',
    ]],
    1 => ['Part 1/30 — Landscape and goals', [
        'WooCommerce wins for open-source flexibility and plugin depth—from small sellers to larger brands. Success requires understanding local buyer behavior: low digital trust, smartphone shopping, and high incomplete-checkout rates.',
        'Delivery loss and fake orders remain top merchant fears. This 30-part playbook covers fake-order prevention, courier history filtering, abandoned-order recovery, and accurate Facebook ads ROAS.',
    ]],
    2 => ['Part 2/30 — COD hidden risks and return math', [
        'COD grows sales but hides three risks: zero buyer commitment, fake/prank orders, and capital lock-in while waiting 3-7 days for courier payouts.',
        'Worked example: Tk 1,000 price, Tk 500 COGS, Tk 200 CPA, Tk 30 packing, Tk 80 delivery, Tk 130 return penalty. At 100% delivery, 10 orders yield about Tk 1,900 profit; at 20% returns profit falls to about Tk 800 (about 58% drop).',
        'Manual call confirm alone is not enough in 2026—use automation that checks courier success history, forces advance delivery fee or OTP for high-risk numbers, and stops shipping junk orders.',
    ]],
    3 => ['Part 3/30 — Courier API + automated fraud detection', [
        'Courier APIs connect WooCommerce to Steadfast, Pathao, and RedX for one-click/bulk booking, tracking IDs, live status, and SMS with tracking links.',
        'Fraud detection uses courier delivery databases: green (80-100% success) can ship COD; yellow (50-79%) needs call/OTP; red (under 50%) should see advance fee prompts.',
        'Example: 10 red-zone customers without automation may create about Tk 1,040 return loss; with automation most fakes drop out and loss approaches zero.',
    ]],
    4 => ['Part 4/30 — One-click fast checkout', [
        'Long WooCommerce forms (8-12 fields) kill mobile conversion. Fast checkout keeps three fields—name, phone, full address—plus Dhaka inside/outside delivery radio buttons.',
        'Compare: traditional 2-3 minutes and about 1.5-2.5% conversion vs 15-20 seconds and about 4-7% with a mobile-optimized form.',
        'Math: 1,000 daily checkout visitors at 3% = 30 orders; at 5.5% = 55 orders—same ad spend, +25 orders/day.',
    ]],
    5 => ['Part 5/30 — Incomplete order recovery', [
        'Bangladesh incomplete/abandoned checkout rates often hit 60-70%. Standard WooCommerce saves nothing until Place Order; smart recovery saves phone/name in real time.',
        'Flow: number typed, backend incomplete order, wait 5-15 minutes, then WhatsApp/SMS recovery. WhatsApp open rates (about 95%) beat email.',
        'Recovering 15% of 1,000 incompletes at Tk 1,000 AOV = Tk 1,50,000 extra revenue without new ad spend.',
    ]],
    6 => ['Part 6/30 — SMS and WhatsApp workflows', [
        'Use SMS for OTP/tracking reliability and WhatsApp for rich, two-way recovery and offers. Build three triggers: instant order confirm, incomplete follow-up, and courier dispatch with tracking.',
        'Personalize with dynamic tags: customer name, order ID, product, tracking link, cart recovery URL.',
    ]],
    7 => ['Part 7/30 — OTP verification', [
        'Anyone can type a random number for COD. OTP forces SMS/WhatsApp code before Place Order. Prefer conditional OTP: high-risk courier history, high AOV, or first-time buyers.',
        'Example: 50 fake numbers x Tk 130 = Tk 6,500 loss vs about Tk 300-400 SMS cost—net save Tk 6,100+/month.',
    ]],
    8 => ['Part 8/30 — Admin mobile app', [
        'Desktop-only ops fail when merchants are at suppliers or on the road. A dedicated admin app beats mobile browser admin with instant push sound, one-swipe status, and in-app courier booking.',
        'Use one-tap call/WhatsApp and show green/red courier history flags before approving parcels.',
    ]],
    9 => ['Part 9/30 — Steadfast and Pathao API setup', [
        'Stop copy-pasting into courier panels. Exchange API keys, map warehouse location, then bulk-select 50-100 orders and send to Steadfast/Pathao in seconds.',
        'Benefits: about 90% time saved, fewer address typos, and one-click shipping labels from WooCommerce.',
    ]],
    10 => ['Part 10/30 — Parcel tracking notifications', [
        'Automate In Transit, Out for Delivery, and Delivered messages. Out-for-delivery alerts get cash ready and cut customer-unavailable returns.',
        'Optional branded tracking page on your domain can add upsell while reducing support load.',
    ]],
    11 => ['Part 11/30 — Post-delivery reviews', [
        'Trigger review requests 24-48 hours after Delivered via WhatsApp/SMS. Route 4-5 star public; 1-3 star to private support. Reward reviews with coupons for repeat purchase.',
    ]],
    12 => ['Part 12/30 — LTV and repeat purchase', [
        'CAC keeps rising—grow LTV with consumption-cycle reminders, RFM segments (VIP/regular/at-risk), and loyalty points with expiry.',
    ]],
    13 => ['Part 13/30 — VIP membership club', [
        'Automate Silver/Gold/Platinum tiers by spend. Unlock early access, birthday perks, secret deals, and zero-friction checkout for top VIPs.',
    ]],
    14 => ['Part 14/30 — Meta Pixel and CAPI', [
        'Browser pixel alone loses many events to iOS/ad blockers. Add Conversions API, sync purchased/incomplete/VIP audiences, build 1% lookalikes, and dedupe with shared event_id.',
    ]],
    15 => ['Part 15/30 — GA4 server-side', [
        'Use server-side GTM/first-party domain to resist ITP/ad blockers. Map view_item, add_to_cart, begin_checkout, purchase. Analyze checkout drop-off, channel attribution, and mobile performance.',
    ]],
    16 => ['Part 16/30 — Google Shopping and PMax', [
        'Automate product feeds every 24h with required attributes (id, title, price BDT, availability). Scale with Performance Max asset groups and audience signals.',
    ]],
    17 => ['Part 17/30 — Inventory automation', [
        'Auto-hold stock on checkout, release on failed OTP/cancel, alert at low stock, capture notify-me leads, and draft POs from sales velocity.',
    ]],
    18 => ['Part 18/30 — WhatsApp+SMS combo engine', [
        'Primary WhatsApp, fallback SMS if undelivered. Central wallet, delivery logs, dedupe, DND windows, and short links protect reach across OTP, shipped, and out-for-delivery events.',
    ]],
    19 => ['Part 19/30 — Messenger/Instagram DM funnels', [
        'Comment-to-inbox, story mention coupons, keyword bots, and 1-click checkout buttons convert social intent in seconds. Respect Meta 24-hour window and escalate angry chats to humans.',
    ]],
    20 => ['Part 20/30 — Email and cart abandonment', [
        'Email still wins for high-ticket/B2B. Run 1h / 24h / 48h cart sequences plus welcome, confirm, post-purchase, win-back. Authenticate SPF/DKIM/DMARC on domain SMTP.',
    ]],
    21 => ['Part 21/30 — Google Ads retargeting and SEO automation', [
        'Dynamic remarketing from GA4 audiences, automated Product schema, XML sitemaps/Indexing API, WebP + lazy-load for mobile speed, and templated meta titles/descriptions.',
    ]],
    22 => ['Part 22/30 — A/B testing and CRO', [
        'Target about 1s load with CDN/caching, critical CSS, deferred scripts. Auto-route traffic to winning variants. Use trust badges, sticky mobile CTA, live purchase proof, and exit-intent offers.',
    ]],
    23 => ['Part 23/30 — AI support and voice agents', [
        'Bangla/Banglish voice bots can confirm orders in about 30s via DTMF. NLP handles dialects; sentiment routes complaints to humans. Auto-retry unanswered fraud-risk calls then hold/cancel with SMS notice.',
    ]],
    24 => ['Part 24/30 — CRM and RFM', [
        'Sync orders into CRM profiles. Score Recency/Frequency/Monetary into VIP, at-risk, new, lost—then automate win-back, onboarding, and predictive replenishment.',
    ]],
    25 => ['Part 25/30 — Courier returns and cancellation prevention', [
        'Auto-consign on Processing, print barcodes, webhook status into WooCommerce, restock on RTS. Pre-ship fraud DB checks, duplicate detection, location risk scoring, and COD reconciliation.',
    ]],
    26 => ['Part 26/30 — Multi-vendor inventory', [
        'Central stock as source of truth across WooCommerce, Daraz, social shops, POS. Auto-split multi-vendor carts, vendor payouts, low-stock POs, and scan-to-pack barcode verification.',
    ]],
    27 => ['Part 27/30 — Profit ledger and tax', [
        'Net profit = price - (COGS + CPA + shipping + gateway fee + RTS allowance). Sync bKash/Nagad fees and Meta spend; configure VAT/BIN invoices and push ledgers to accounting tools.',
    ]],
    28 => ['Part 28/30 — Security and compliance', [
        'WAF/rate-limit, harden wp-admin with 2FA, throttle OTP abuse, tokenize payments, hourly encrypted backups, SSL, and customer data deletion rights.',
    ]],
    29 => ['Part 29/30 — KPI dashboard (CAC/LTV/ROAS)', [
        'Track AOV, CAC, LTV:CAC (target 3:1+), RTS (under 8% with automation). Blend Meta/Google spend, cohort LTV, and nightly WhatsApp/Telegram CEO summaries.',
    ]],
    30 => ['Part 30/30 — Scaling roadmap and future-proofing', [
        'Quarterly audit APIs, DB health, security access, and profit reconciliation. Scale phases: foundation, full automation, hyper-scale/headless.',
        'Automation is not only time saved—it makes the business measurable, durable, and profitable. Implement this architecture with WooEasyLife / your WooCommerce stack.',
    ]],
];

$cluster = [
    'en_woocommerce_bangladesh' => range(0, 30),
    'en_steadfast_integration' => [3, 9, 10, 25],
    'en_pathao_courier_guide' => [3, 9, 10, 25],
    'en_redx_courier_guide' => [3, 10, 25],
    'en_woocommerce_mobile_app' => [8],
    'en_customer_verification' => [7, 3],
    'en_cod_return_reduction' => [2, 3, 25],
    'en_woocommerce_notifications' => [5, 6, 10, 11, 18],
    'en_facebook_ads_for_woocommerce' => [14, 15, 16, 21],
];

$focus = [
    'en_woocommerce_bangladesh' => 'Complete WooCommerce Bangladesh master guide (2026): all 30 parts—from COD risk math to courier APIs, OTP, messaging, Meta CAPI, GA4, inventory, security, and scaling.',
    'en_steadfast_integration' => 'Steadfast courier API for WooCommerce: one-click/bulk booking, tracking IDs, status sync, and return tracking.',
    'en_pathao_courier_guide' => 'Pathao courier API: Client ID/Secret connect, bulk entry, tracking, and COD operations.',
    'en_redx_courier_guide' => 'RedX API and auto entry: confirm, book, track, fewer returns.',
    'en_woocommerce_mobile_app' => 'Admin mobile app: push alerts, one-tap call/WhatsApp, fraud flags, courier entry.',
    'en_customer_verification' => 'OTP plus courier success-rate zones to stop fake COD orders.',
    'en_cod_return_reduction' => 'COD return math and RTS prevention with fraud checks and courier automation.',
    'en_woocommerce_notifications' => 'SMS/WhatsApp/email automation for recovery, tracking, and reviews.',
    'en_facebook_ads_for_woocommerce' => 'Meta Pixel + CAPI, audiences, GA4 server-side, Google Shopping/PMax.',
];

$existing = require __DIR__.'/../config/seo_cluster_content.php';

foreach ($cluster as $key => $nums) {
    $sections = [['heading' => 'Quick answer', 'paragraphs' => [$focus[$key]]]];
    foreach ($nums as $n) {
        $sections[] = [
            'heading' => $enParts[$n][0],
            'paragraphs' => $enParts[$n][1],
        ];
    }
    $existing[$key] = $sections;
}

$file = __DIR__.'/../config/seo_cluster_content.php';
file_put_contents(
    $file,
    "<?php\n\n/** Hub-and-spoke long-form content (BN master guide + EN full coverage). */\n\nreturn "
    .var_export($existing, true)
    .";\n"
);

echo 'EN pillar sections='.count($existing['en_woocommerce_bangladesh']).PHP_EOL;
