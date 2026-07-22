<?php

return array (
  0 =>
  array (
    'heading' => 'Quick answer: Facebook and Meta ads for WooCommerce Bangladesh COD',
    'paragraphs' =>
    array (
      0 => 'Facebook and Instagram ads drive most Bangladesh WooCommerce traffic, but browser Pixel alone under-reports purchases on mobile COD stores. Pair Meta Pixel with Conversions API (CAPI), sync clean audiences, map GA4 server-side events, and never scale budget while fake COD and RTS eat margin.',
      1 => 'Measure real ROAS on /en/ads-roas-calculator; subtract return loss on /en/return-loss-calculator before increasing spend. Bangla guide: /facebook-ads-for-woocommerce. Hub: /en/woocommerce-bangladesh.',
    ),
  ),
  1 =>
  array (
    'heading' => 'Why browser Pixel alone lies on Bangladesh COD stores',
    'paragraphs' =>
    array (
      0 => 'iOS privacy changes, in-app browsers, ad blockers, and slow thank-you page loads drop Purchase and AddToCart events. Meta optimizes toward what it sees—often incomplete checkout or low-quality clicks—while your WooCommerce dashboard shows different order counts.',
      1 => 'COD adds another gap: Pixel fires on order placed, but profit happens on delivered and paid parcels. If 20% RTS, reported ROAS from Pixel can look healthy while bank balance shrinks. Fix measurement before scaling campaigns.',
      2 => 'Layer server-side CAPI so WooCommerce (or WooEasyLife) sends Purchase, InitiateCheckout, and AddToCart from your server with hashed phone/email for Event Match Quality. Redundant Pixel + CAPI with shared event_id avoids double counting.',
    ),
  ),
  2 =>
  array (
    'heading' => 'Meta Pixel + CAPI setup for WooCommerce',
    'paragraphs' =>
    array (
      0 => 'Install Pixel for front-end signal; add CAPI token and test events in Events Manager. Map WooCommerce hooks: AddToCart, InitiateCheckout, Purchase—with value, currency BDT, content IDs, and order_id for deduplication.',
      1 => 'Send enhanced matching fields (phone, email, name, city) hashed per Meta spec. Higher match quality improves attribution and lookalike seed quality for Bangladesh audiences.',
      2 => 'Exclude or down-weight events from known test numbers and staff IPs. Sync purchased customers to Custom Audiences for retention; sync incomplete checkouts for recovery campaigns tied to /en/woocommerce-notifications.',
    ),
  ),
  3 =>
  array (
    'heading' => 'Do not scale ads on fake COD and prank orders',
    'paragraphs' =>
    array (
      0 => 'Boosting budget while red-zone numbers ship trains Meta to find more low-intent COD clickers. Fake and refused parcels inflate CPA and return fees (often ৳130+ per RTS) without revenue. Fraud filters are an ads problem, not only an ops problem.',
      1 => 'Before raising daily budget, run /en/bd-fraud-checker on recent return clusters, enable /en/fake-order-protection (OTP, duplicates, blacklist), and read /en/cod-return-reduction for confirm-before-ship rules. Clean delivery rate stabilizes lookalikes and CAPI Purchase quality.',
      2 => 'If returns spike after a creative wins, pause scale until /en/return-loss-calculator shows acceptable net margin—not until Meta dashboard ROAS looks green.',
    ),
  ),
  4 =>
  array (
    'heading' => 'Clean ROAS with delivered-revenue math',
    'paragraphs' =>
    array (
      0 => 'Reported ROAS = ad spend ÷ Pixel purchases. Real ROAS for COD = ad spend ÷ delivered and collected revenue minus COGS, packing, forward and return courier fees, and refunds. Use /en/ads-roas-calculator with your actual fee structure.',
      1 => 'Example mindset: ৳50,000 ad spend, 100 orders placed, 80 delivered, 20 RTS. Pixel may count 100 purchases; cash and profit behave like 80. Subtract 20 × (CPA share + return penalty + packing) before declaring a winning campaign.',
      2 => 'Feed ads decisions from weekly delivered-revenue ledger, not daily order notifications alone. Align finance and media buyer on the same definition of “conversion.”',
    ),
  ),
  5 =>
  array (
    'heading' => 'Audiences, lookalikes, and recovery funnels',
    'paragraphs' =>
    array (
      0 => 'Seed 1% lookalikes from purchasers with high delivery success—not from all checkouts. Build exclusions: purchasers last 30 days, blacklisted phones, and low courier success segments where policy allows.',
      1 => 'Retarget incomplete checkouts with WhatsApp/SMS first (/en/woocommerce-notifications); use Meta retargeting as secondary. Cart abandonment ads on junk numbers waste reach.',
      2 => 'Admins approving orders from /en/woocommerce-mobile-app faster reduces lag between click and confirm—better for both customer experience and CAPI signal timing.',
    ),
  ),
  6 =>
  array (
    'heading' => 'GA4 server-side and cross-channel truth',
    'paragraphs' =>
    array (
      0 => 'Add GA4 with server-side tagging or first-party domain to reduce ITP/ad-block loss. Map view_item, add_to_cart, begin_checkout, purchase with item-level parameters. Compare Meta, GA4, and WooCommerce order reports weekly—large gaps mean fix tracking before budget tests.',
      1 => 'Use GA4 explorations for mobile vs desktop checkout drop-off and channel assist. Bangladesh traffic is overwhelmingly mobile; broken mobile checkout destroys ROAS regardless of creative quality.',
      2 => 'Connect messaging and delivery data so “purchase” in analytics can later be segmented by delivered vs returned when you export ops metrics—bridging marketing and /en/cod-return-reduction KPIs.',
    ),
  ),
  7 =>
  array (
    'heading' => 'Google Shopping, Performance Max, and next steps',
    'paragraphs' =>
    array (
      0 => 'Beyond Meta, automate product feeds to Google Merchant Center with accurate BDT price, availability, and image links. Performance Max can scale when feed and landing pages are clean—apply the same fraud and return discipline; do not import junk COD into “all purchasers” signals.',
      1 => 'Brief PMax setup: strong asset groups, audience signals from best categories, exclude out-of-stock SKUs automatically, and pause when inventory alerts fire. Organic schema and sitemap hygiene support paid efficiency on the same catalog.',
      2 => 'Implement tracking and fraud stack in WooEasyLife via /pricing: CAPI-friendly events, courier sync, notifications, and calculators in one place. Bangla deep dive: /facebook-ads-for-woocommerce. Master playbook: /en/woocommerce-bangladesh.',
    ),
  ),
);
