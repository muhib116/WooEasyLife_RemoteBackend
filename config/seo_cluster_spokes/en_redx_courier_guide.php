<?php

return array (
  0 =>
  array (
    'heading' => 'RedX + WooCommerce for Bangladesh COD stores',
    'paragraphs' =>
    array (
      0 => 'RedX serves a wide mix of Facebook shops and WooCommerce COD brands with competitive rates on many district routes. The operational bottleneck is the same as every courier: manually copying customer details into the RedX merchant panel after each confirmation. Typos in phone or area codes trigger failed attempts, return penalties, and angry customers waiting for parcels that never arrive.',
      1 => 'This RedX courier guide explains how to collect API credentials from the RedX merchant panel, connect them in WooEasyLife, map your pickup location, book parcels from WooCommerce (single or bulk), store tracking IDs on orders, and sync status for automated customer alerts. Run /en/bd-fraud-checker and /en/fake-order-protection before you auto-book risky COD numbers.',
      2 => 'Bangla version: /redx-courier-guide. Full ops context: /en/woocommerce-bangladesh. Confirm → book workflow: /en/courier-auto-entry.',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/courier-auto-entry.jpg',
        'alt' => 'Confirm order then RedX auto parcel entry flow',
        'caption' => 'Confirm once → RedX parcel created → tracking ID saved on the WooCommerce order',
      ),
    ),
  ),
  1 =>
  array (
    'heading' => 'Why RedX API integration beats manual merchant panel entry',
    'paragraphs' =>
    array (
      0 => 'Manual RedX booking at 40+ orders per day means constant tab-switching: WooCommerce order → RedX panel → paste name, phone, address, weight, COD → wait for tracking → paste back. Staff fatigue causes wrong COD amounts, duplicate entries, and parcels booked to outdated addresses after a customer edits checkout notes.',
      1 => 'RedX API integration sends validated order fields from WooEasyLife after you confirm. RedX returns a tracking/consignment ID attached to the WooCommerce order. Status updates can flow back for notifications and RTS handling. Result: roughly 90% less booking time, fewer typos, and one source of truth for which orders already shipped.',
      2 => 'Compare RedX versus other couriers on your city mix with /en/courier-charge-calculator. If return fees already hurt margins, run the numbers on /en/return-loss-calculator before you scale RedX automation.',
    ),
  ),
  2 =>
  array (
    'heading' => 'RedX API token/key from the merchant panel',
    'paragraphs' =>
    array (
      0 => 'RedX issues API credentials through the merchant panel—typically an API token/key pair or equivalent access credentials depending on your account type. Log into the RedX merchant dashboard, open the API or Developer section, and collect the API token/key values shown there. RedX may label fields differently across account tiers; copy exactly what the panel displays.',
      1 => 'Keep credentials private and rotate them if a team member with panel access leaves. In WooEasyLife open Courier Settings → RedX, paste the API token/key, enable RedX, and run a connection test. Then set your default pickup store/warehouse so RedX parcels inherit the correct hub context.',
      2 => 'A failed connection test with valid-looking credentials usually means API access is not yet enabled on the RedX account—contact RedX support or your account manager before retrying. After green status, book one test parcel before bulk mode.',
    ),
  ),
  3 =>
  array (
    'heading' => 'Step-by-step: connect RedX to WooCommerce',
    'paragraphs' =>
    array (
      0 => '1) Ensure your RedX merchant account is active and API access is enabled. 2) Collect API token/key from the RedX merchant panel. 3) In WooEasyLife → Courier Settings, enable RedX and paste credentials. 4) Map default store/warehouse location. 5) Run connection test. 6) Confirm a test order, send to RedX, verify tracking ID saves. 7) Enable tracking message templates on /en/woocommerce-notifications.',
      1 => 'Safety order matters: /en/bd-fraud-checker for courier history → /en/fake-order-protection for OTP and blacklists → RedX auto entry on confirm only. Shipping unverified COD because “RedX is connected” multiplies return loss faster than manual booking ever could.',
      2 => 'Running multiple couriers? Pair this guide with /en/steadfast-integration and /en/pathao-courier-guide. Pick a default courier per zone so packers and booking staff follow one clear rule.',
    ),
  ),
  4 =>
  array (
    'heading' => 'Confirm → RedX auto entry and bulk send',
    'paragraphs' =>
    array (
      0 => 'Target workflow: new order → fraud zone check → call/OTP if needed → confirm → WooEasyLife pushes structured data to RedX → tracking ID on order → label print → pickup. Staff stop living in two panels for every parcel.',
      1 => 'Bulk booking: select confirmed orders in WooCommerce and send to RedX in one batch. Enable bulk only after single-order flow is reliable. Export or review failed bookings—bad area codes, empty phone fields, COD mismatches—fix the order, then retry once to avoid duplicate consignments.',
      2 => 'On-the-go merchants can confirm or hold from /en/woocommerce-mobile-app, then trigger RedX entry after approval. Recovery and tracking automation still live in /en/woocommerce-notifications.',
    ),
  ),
  5 =>
  array (
    'heading' => 'Address mapping, weight, and COD accuracy for RedX',
    'paragraphs' =>
    array (
      0 => 'RedX booking quality depends on clean address data. Map WooCommerce city, zone, and area fields (or custom checkout fields) to values RedX accepts. Vague addresses—“beside school, Bogura”—fail validation or route to the wrong hub.',
      1 => 'COD amount must match what the rider collects, including any delivery charge you promised. Mismatch creates disputes at the door and reconciliation headaches later. Keep product lines and shipping fees consistent before confirm.',
      2 => 'Add short product or weight notes on high-SKU orders so RedX entry and packing stay aligned. Plan charges ahead with /en/courier-charge-calculator when testing new routes.',
    ),
  ),
  6 =>
  array (
    'heading' => 'Tracking sync, notifications, and return handling',
    'paragraphs' =>
    array (
      0 => 'Store RedX tracking/consignment IDs on each WooCommerce order. Automated In Transit and Out for Delivery messages cut “where is my order?” tickets and reduce customer-unavailable returns because buyers expect the rider call.',
      1 => 'Set up WhatsApp-first templates with SMS fallback on /en/woocommerce-notifications. Include order ID, COD total, and a tracking link. When RedX marks RTS or cancel, restock and flag the order so /en/cod-return-reduction workflows stay accurate.',
      2 => 'Branded tracking on your domain (optional) keeps customers on your site and opens room for upsell while support load drops.',
    ),
  ),
  7 =>
  array (
    'heading' => 'Verify customers before RedX auto-booking',
    'paragraphs' =>
    array (
      0 => 'RedX API speed is an advantage only after verification. Pull courier success history on /en/bd-fraud-checker: green (roughly 80–100% success) can ship COD quickly; yellow (50–79%) needs call confirm or OTP; red (under 50%) should see advance fee prompts or hold via /en/fake-order-protection.',
      1 => 'Ten red-zone parcels shipped on autopilot can cost more in reverse fees and packaging than dozens of green-zone deliveries earn. Automate RedX for clean orders; add friction for risky ones.',
      2 => 'See /en/woocommerce-bangladesh for the full fraud stack. Quantify exposure with /en/return-loss-calculator before increasing ad spend on COD campaigns.',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'Multi-layer verification before RedX courier auto booking',
        'caption' => 'Courier history + OTP/blacklist + confirm — then RedX auto entry',
      ),
    ),
  ),
  8 =>
  array (
    'heading' => 'Troubleshooting RedX API issues',
    'paragraphs' =>
    array (
      0 => 'Auth failures: re-copy API token/key from the RedX merchant panel, confirm API access is active, and update WooEasyLife if RedX rotated credentials. Trailing spaces in pasted keys are a common silent failure.',
      1 => 'Area or city rejections: validate checkout mapping or collect a clearer address from the customer. Duplicate parcels: check for an existing tracking ID before resending. COD errors: reconcile order totals, then rebook once.',
      2 => 'Cross-check Steadfast patterns on /en/steadfast-integration or Pathao on /en/pathao-courier-guide. Daily ops overview: /en/courier-auto-entry. Ready for production? Start from /pricing.',
    ),
  ),
  9 =>
  array (
    'heading' => 'Next steps for RedX + WooCommerce',
    'paragraphs' =>
    array (
      0 => 'Launch checklist: RedX API token/key saved → connection test passed → warehouse mapped → test parcel delivered → fraud checks enabled → notifications live → bulk limited to confirmed orders.',
      1 => 'Related guides: /en/courier-auto-entry (daily flow), /en/woocommerce-mobile-app (confirm on the go), /en/cod-return-reduction (RTS control), /en/woocommerce-bangladesh (full playbook). Bangla mirror: /redx-courier-guide.',
    ),
  ),
);
