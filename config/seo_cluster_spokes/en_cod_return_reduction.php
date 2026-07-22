<?php

return array (
  0 =>
  array (
    'heading' => 'Quick answer: reduce COD returns in Bangladesh WooCommerce',
    'paragraphs' =>
    array (
      0 => 'Cash-on-delivery (COD) drives most Bangladesh WooCommerce sales, but return-to-sender (RTS) parcels quietly erase profit. This guide covers the math (৳ or Tk), why phone confirmation alone fails at scale, fraud zones, OTP/advance-fee rules, out-for-delivery messaging, and courier auto entry only after verified confirm.',
      1 => 'Start with /en/bd-fraud-checker, layer /en/fake-order-protection and /en/customer-verification, then ship via /en/courier-auto-entry. Measure monthly loss on /en/return-loss-calculator. Bangla version: /cod-return-reduction. Full hub: /en/woocommerce-bangladesh.',
    ),
  ),
  1 =>
  array (
    'heading' => 'The hidden math: how a 20% return rate cuts profit in half',
    'paragraphs' =>
    array (
      0 => 'Many WooCommerce merchants in Bangladesh celebrate order volume while ignoring return economics. COD has no upfront payment, so buyers feel less obligation to receive the parcel. Fake orders, prank numbers, and last-minute refusals all show up as courier returns—not as cancelled carts.',
      1 => 'Model every order with selling price, COGS, ad CPA, packaging, forward delivery, and reverse return penalty. Example: ৳1,000 selling price, ৳500 COGS, ৳200 CPA, ৳30 packing, ৳80 forward delivery, ৳130 return fee. Ten orders at 100% delivery ≈ ৳1,900 net profit. At 20% returns (8 delivered, 2 RTS), profit drops to ≈ ৳800—a 58% collapse from the same ad spend.',
      2 => 'That is why return reduction is an ops and fraud problem, not only a “call every customer” problem. Before scaling Facebook ads, run your numbers on /en/return-loss-calculator and compare reported ROAS on /en/ads-roas-calculator against real delivered COD.',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/cod-loss-math.jpg',
        'alt' => 'COD success vs return profit comparison for Bangladesh ecommerce',
        'caption' => 'Successful deliveries vs returns — how net profit flips when RTS rises',
      ),
    ),
  ),
  2 =>
  array (
    'heading' => 'Why manual call confirmation alone fails in 2026',
    'paragraphs' =>
    array (
      0 => 'Calling every COD order feels safe until volume hits 40–100+ per day. Staff burn hours on duplicate calls, miss yellow-zone patterns, and still ship red-zone numbers because there is no shared courier history at checkout. Competitors and pranksters learn that a polite “yes” on the phone costs them nothing.',
      1 => 'Phone confirm also breaks on timing: the customer who said yes on Monday may refuse on Thursday when the rider arrives with cash in hand. Without out-for-delivery reminders via /en/woocommerce-notifications, “customer unavailable” returns stay high even after a successful call.',
      2 => 'Replace one-layer calling with a stack: courier success-rate check → conditional OTP → duplicate/blacklist rules → confirm → courier booking. Setup guides: /en/fake-order-protection and /en/customer-verification. Free history lookup: /en/bd-fraud-checker.',
    ),
  ),
  3 =>
  array (
    'heading' => 'Green, yellow, and red fraud zones from courier history',
    'paragraphs' =>
    array (
      0 => 'Bangladesh couriers (Steadfast, Pathao, RedX) hold billions of delivery outcomes tied to mobile numbers. WooEasyLife reads success-rate signals before you confirm: green (roughly 80–100% success) can ship COD fast; yellow (50–79%) needs call or OTP; red (under 50%) should see advance delivery fee, hold, or block.',
      1 => 'Worked example: 10 red-zone orders shipped without filters → 8 returns × ৳130 ≈ ৳1,040 reverse-fee loss plus wasted packaging and CPA. With automation, most fakes drop out when advance fee is required; serious buyers pay; net loss approaches zero on that batch.',
      2 => 'Zone rules are not punishment—they protect margin. Pair history with duplicate IP/phone detection and shared blacklist checks from /en/fake-order-protection. Deep dive on layers in the hub /en/woocommerce-bangladesh.',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'Multi-layer COD fraud prevention: history, OTP, block, confirm',
        'caption' => 'Fraud layers: courier history → OTP → blacklist → confirm → ship',
      ),
    ),
  ),
  4 =>
  array (
    'heading' => 'OTP and partial advance fee for high-risk COD',
    'paragraphs' =>
    array (
      0 => 'Checkout OTP proves the buyer controls the phone—not that they will accept the parcel. Use conditional OTP: first-time buyers, high AOV, yellow/red courier history, or suspicious address patterns. Full rules: /en/customer-verification.',
      1 => 'Partial advance (delivery charge or small deposit via bKash/Nagad/card) filters prank orders cheaply. A ৳80–150 advance costs real money to fake; SMS OTP cost (≈ ৳3–4 per message) is tiny next to a ৳130 return penalty plus ৳200 CPA on a refused parcel.',
      2 => 'Do not force advance on every order—that hurts conversion on clean green-zone repeat buyers. Automate tiered rules so ops stays fast on safe numbers and slow on risky ones.',
    ),
  ),
  5 =>
  array (
    'heading' => 'Out-for-delivery messages that cut “customer unavailable” returns',
    'paragraphs' =>
    array (
      0 => 'Many RTS parcels are not fraud—they are coordination failures. The rider calls from an unknown number; the customer has no cash ready; they are at work and miss the visit. Automated Out for Delivery WhatsApp/SMS fixes that when wired to courier webhooks.',
      1 => 'Template pattern: “Good morning [Name], your order [ID] is out for delivery today. Please keep ৳[COD amount] ready and answer calls from our courier partner. Track: [link].” Send via /en/woocommerce-notifications with WhatsApp primary and SMS fallback.',
      2 => 'Merchants who add this step after confirm often see fewer customer-unavailable returns without changing product or ad creative. It complements fraud filters—it does not replace them.',
    ),
  ),
  6 =>
  array (
    'heading' => 'Courier auto entry only after confirm—not on every new order',
    'paragraphs' =>
    array (
      0 => 'Connecting Pathao, Steadfast, or RedX API via /en/courier-auto-entry saves hours of copy-paste, but auto-booking every incoming order ships junk to the courier. Ideal flow: order arrives → history/OTP rules → human or rule-based confirm → API creates consignment → tracking ID saved → packing label.',
      1 => 'Bulk booking is for confirmed batches only. Failed API rows (bad zone, missing phone, COD mismatch) should surface before retry so you do not duplicate consignments. When status returns RTS, restock and log loss—feeds /en/return-loss-calculator monthly reviews.',
      2 => 'Bangla mirror and extended courier guides live under /cod-return-reduction and the /en/woocommerce-bangladesh hub.',
    ),
  ),
  7 =>
  array (
    'heading' => 'Measure, iterate, and scale without bleeding margin',
    'paragraphs' =>
    array (
      0 => 'Track RTS rate, return-fee spend, and profit per delivered order—not just daily order count. Use /en/return-loss-calculator with your real COGS, CPA, packing, and courier fees. If returns rise after a promo, pause budget increases until /en/ads-roas-calculator shows clean delivered revenue.',
      1 => 'Weekly ops rhythm: review red-zone blocks, yellow-zone call outcomes, top return reasons from courier notes, and OTP/advance conversion. Tune rules instead of hiring more callers.',
      2 => 'Implement the stack in WooEasyLife: start free on /en/bd-fraud-checker, then /pricing for trial or subscription. You get fraud filters, courier API, and messaging in one WooCommerce Bangladesh workflow—not disconnected plugins.',
    ),
  ),
);
