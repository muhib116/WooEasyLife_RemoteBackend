<?php

return array (
  0 =>
  array (
    'heading' => 'Quick answer: SMS, WhatsApp, and email for WooCommerce Bangladesh',
    'paragraphs' =>
    array (
      0 => 'Bangladesh buyers live on mobile messaging—not email inboxes. Automate incomplete checkout recovery, order confirmation, courier tracking (In Transit / Out for Delivery / Delivered), and post-delivery review requests. Use WhatsApp as primary channel with SMS fallback and a branded tracking page on your domain.',
      1 => 'Pair messaging with /en/courier-auto-entry for live status triggers and /en/cod-return-reduction to cut RTS. Bangla guide: /woocommerce-notifications. Hub: /en/woocommerce-bangladesh.',
    ),
  ),
  1 =>
  array (
    'heading' => 'Why messaging matters more than email for BD WooCommerce',
    'paragraphs' =>
    array (
      0 => 'Standard WooCommerce email notifications open at low rates on mobile-first traffic. WhatsApp open rates often exceed 90% for order updates; SMS still wins for OTP and fallback when WhatsApp is unavailable. Merchants who rely on email alone lose incomplete recoveries and repeat “where is my order?” support tickets.',
      1 => 'A single rules engine should route events—Incomplete, Processing, Shipped, Out for Delivery, Delivered, Returned—to the right template and channel. That keeps brand voice consistent whether the customer ordered from Facebook, Instagram, or your store.',
      2 => 'Before you scale ads on /en/facebook-ads-for-woocommerce, fix post-click experience: fast checkout, fraud filters (/en/bd-fraud-checker), and reliable notifications so paid traffic converts and receives parcels.',
    ),
  ),
  2 =>
  array (
    'heading' => 'Incomplete order recovery: capture phone early, recover in 5–15 minutes',
    'paragraphs' =>
    array (
      0 => 'Bangladesh incomplete checkout rates often reach 60–70%. Default WooCommerce saves nothing until Place Order—so you lose the customer who typed a number but got distracted. Smart recovery saves name/phone in real time, waits 5–15 minutes, then sends a WhatsApp or SMS with a one-tap finish link.',
      1 => 'Sequence example: Message 1 (10 min, WhatsApp)—“Hi [Name], you left [Product] in checkout. Complete in two taps: [recovery link].” Message 2 (2 hours, optional)—small incentive such as free delivery code if margin allows. Recovering 15% of 1,000 incompletes at ৳1,000 AOV adds ৳1,50,000/month without new ad spend.',
      2 => 'Keep sequences short (1–2 touches) to avoid spam reports. Respect quiet hours and dedupe if the customer completes the order between messages.',
    ),
  ),
  3 =>
  array (
    'heading' => 'Order confirmation: set expectations before you pack',
    'paragraphs' =>
    array (
      0 => 'Instant confirmation after Place Order reduces anxiety and wrong-number disputes. Include order ID, product summary, COD amount, delivery area, and support contact. If you use OTP at checkout (/en/fake-order-protection), confirmation reinforces that the order is real and queued for verification.',
      1 => 'For yellow-zone numbers, confirmation can note “Our team may call to verify”—so the customer expects a call instead of ignoring it. Green-zone repeat buyers get a shorter confirm and faster path to /en/courier-auto-entry.',
      2 => 'Admins on the road should approve holds from /en/woocommerce-mobile-app so confirmation and courier booking are not delayed until someone opens a laptop.',
    ),
  ),
  4 =>
  array (
    'heading' => 'Tracking lifecycle: In Transit, Out for Delivery, Delivered',
    'paragraphs' =>
    array (
      0 => 'When courier webhooks update status, sync WooCommerce and notify automatically. In Transit: parcel left hub—send tracking link. Out for Delivery: rider en route—ask customer to keep COD cash ready and answer calls (cuts customer-unavailable returns; see /en/cod-return-reduction). Delivered: thank-you plus optional review ask in 24–48 hours.',
      1 => 'Dynamic tags: customer name, order ID, product, COD amount, courier name, tracking URL. Templates must match what the rider will collect—COD mismatches create refused deliveries and support loops.',
      2 => 'Connect API booking first via /en/courier-auto-entry so tracking IDs exist before messaging fires. Manual booking without stored IDs breaks automation.',
    ),
  ),
  5 =>
  array (
    'heading' => 'WhatsApp primary, SMS fallback, and delivery logs',
    'paragraphs' =>
    array (
      0 => 'Route WhatsApp Business API (or approved provider) as primary for rich links and read receipts. If WhatsApp fails or the number is not on WhatsApp, fall back to SMS within minutes—not hours. Log every attempt: sent, delivered, failed, reason.',
      1 => 'Central wallet and DND windows protect deliverability. Dedupe prevents double Out for Delivery alerts when couriers ping status twice. Short links with your domain build trust versus generic URL shorteners.',
      2 => 'OTP and transactional messages may need separate sender IDs per local rules—keep marketing blasts off the same route as order alerts.',
    ),
  ),
  6 =>
  array (
    'heading' => 'Branded tracking page on your WooCommerce domain',
    'paragraphs' =>
    array (
      0 => 'Sending customers only to a courier’s third-party site leaks brand and upsell opportunity. Host a track page on your domain (e.g. yourstore.com/track-order): customer enters order ID + phone, sees live status pulled from courier sync.',
      1 => 'Add related products, support chat, or campaign banners on that page—merchants often see 5–10% extra interest without additional ad cost. The page also deflects repetitive inbox DMs after you promote it in every shipped message.',
      2 => 'SEO and ads alignment: clean on-site experience supports /en/facebook-ads-for-woocommerce conversion quality and reduces refund of ad spend into support labor.',
    ),
  ),
  7 =>
  array (
    'heading' => 'Reviews, returns, and getting started with WooEasyLife',
    'paragraphs' =>
    array (
      0 => 'After Delivered, trigger review requests on WhatsApp/SMS. Route 4–5 star feedback to public channels; route 1–3 star to private support first. Optional small coupon for next COD order builds repeat LTV.',
      1 => 'When status is Returned, notify ops and optionally send a polite message with support link—some RTS is fixable address issues, not malice. Tie return spikes to /en/cod-return-reduction playbooks and fraud checks on /en/bd-fraud-checker.',
      2 => 'Start on /pricing: connect WooCommerce, enable courier sync (/en/courier-auto-entry), configure templates, test one order end-to-end. Bangla walkthrough: /woocommerce-notifications. Full 30-part context: /en/woocommerce-bangladesh.',
    ),
  ),
);
