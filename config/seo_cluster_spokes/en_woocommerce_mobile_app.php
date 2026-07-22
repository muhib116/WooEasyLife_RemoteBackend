<?php

return array (
  0 => 
  array (
    'heading' => 'WooCommerce mobile app — run COD ops without a laptop',
    'paragraphs' => 
    array (
      0 => 'Bangladesh Facebook/Instagram and WooCommerce COD sellers often leave the desk for suppliers or pickups—while orders keep arriving. Mobile-browser WooCommerce admin is slow for find → call → book. A dedicated admin app puts push sound, one-tap call/WhatsApp, fraud flags, and post-confirm courier entry in one place so response time drops.',
      1 => 'This guide covers app vs browser admin, which alerts to enable, how to approve/hold using green/red history, and how to pair the app with courier auto entry. Bangla: /woocommerce-mobile-app. Full system context: /en/woocommerce-bangladesh.',
      2 => 'Start free number checks on /en/bd-fraud-checker. Checkout protection: /en/fake-order-protection. Daily confirm → book: /en/courier-auto-entry.',
    ),
  ),
  1 => 
  array (
    'heading' => 'Mobile browser admin vs dedicated app',
    'paragraphs' => 
    array (
      0 => 'Browser backend: notifications depend on email/refresh, status updates need many taps, courier booking means copy-paste in another tab, and heavy pages waste data and time.',
      1 => 'Admin mobile app: instant push with sound, one-swipe/tap status, in-app call/WhatsApp and courier entry, lightweight UI—so you miss fewer orders during supplier meetings or parcel pickups.',
      2 => 'At a glance: notifications — slow in browser, instant in app. Call/message — copy number vs one-tap. Fraud signal — separate tool vs flags on the order card. Courier — manual panel vs confirm → auto entry.',
    ),
  ),
  2 => 
  array (
    'heading' => 'Realtime push: new orders and incomplete alerts',
    'paragraphs' => 
    array (
      0 => 'New orders should push with sound—waiting on email causes late confirms. Faster confirm means faster pack/ship; COD delays lose customers to other pages.',
      1 => 'Incomplete alerts matter more: if a shopper enters a number but drops checkout, the app notifies so you can call/message and recover. Full messaging automation: /en/woocommerce-notifications.',
      2 => 'Avoid alert spam—prioritize new COD and incompletes; mute low-priority status noise at night so staff stay sharp.',
    ),
  ),
  3 => 
  array (
    'heading' => 'One-tap call and WhatsApp confirmation',
    'paragraphs' => 
    array (
      0 => 'Direct Call from the order card dials the customer without copy-paste. WhatsApp shortcuts send saved confirmation templates: order ID, product, COD amount, delivery area.',
      1 => 'On yellow-zone or new customers, call/message first—then approve. On green history, confirm fast and ship via /en/courier-auto-entry. On red zone, advance fee or hold—see /en/customer-verification and /en/fake-order-protection.',
      2 => 'Team tip: note who called and what was said so you do not double-call. App + notification stack together cuts support load.',
    ),
  ),
  4 => 
  array (
    'heading' => 'Fraud flags in the app: green vs red history',
    'paragraphs' => 
    array (
      0 => 'Show courier success-rate flags when the order opens. Green (high success): approve fast. Red (repeat refuse/low success): call for advance delivery fee or hold. Yellow: confirm with caution.',
      1 => 'Without mobile signals, staff ship everything—and return fees plus ad CPA erase profit. Free checks: /en/bd-fraud-checker. Monthly loss: /en/return-loss-calculator.',
      2 => 'The app shows signals; you decide. Auto-ship only verified/green orders. Full COD math: /en/woocommerce-bangladesh and /en/cod-return-reduction.',
    ),
    'figures' => 
    array (
      0 => 
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'Fraud layers and approve flow on mobile',
        'caption' => 'Push → history flag → call/OTP → confirm → courier entry',
      ),
    ),
  ),
  5 => 
  array (
    'heading' => 'Courier entry from mobile (Pathao / Steadfast / RedX)',
    'paragraphs' => 
    array (
      0 => 'After confirm, trigger courier entry from the app/workflow—less panel typing. API setup: /en/pathao-courier-guide, /en/steadfast-integration, /en/redx-courier-guide. Daily flow: /en/courier-auto-entry.',
      1 => 'Use bulk carefully on mobile—test single orders first. When tracking IDs save, notify customers via /en/woocommerce-notifications.',
      2 => 'Packing can still happen in the warehouse; the app’s job is fast decisions and booking triggers. Verify area/COD before send.',
    ),
    'figures' => 
    array (
      0 => 
      array (
        'src' => '/images/seo/cluster/courier-auto-entry.jpg',
        'alt' => 'Confirm then courier auto entry',
        'caption' => 'Approve on mobile → parcel created → tracking ID saved on the order',
      ),
    ),
  ),
  6 => 
  array (
    'heading' => 'Safe mobile workflow checklist',
    'paragraphs' => 
    array (
      0 => '1) Push on for new + incomplete. 2) Open order and read fraud flags (/en/bd-fraud-checker history). 3) Yellow/red → call or OTP (/en/customer-verification, /en/fake-order-protection). 4) Confirm. 5) Courier entry (/en/courier-auto-entry). 6) Tracking message (/en/woocommerce-notifications).',
      1 => 'Do not: auto-ship red numbers; reassign without notes; run production bulk without a connection test. If returns rise, measure with /en/return-loss-calculator.',
      2 => 'Split roles—who can approve vs who only calls—to cut mistaken bookings.',
    ),
  ),
  7 => 
  array (
    'heading' => 'Setup and next steps',
    'paragraphs' => 
    array (
      0 => 'Start a WooEasyLife trial (/pricing), connect WooCommerce, set courier APIs, enable fake-order protection, then log into the mobile app and allow push. Validate push → call → confirm → entry with one test order.',
      1 => 'Troubleshooting: no push → OS notification permission; call fails → phone format; entry fails → courier credentials/area mapping.',
      2 => 'Keep reading: /en/woocommerce-bangladesh hub, /en/courier-auto-entry, /en/woocommerce-notifications, Bangla mirror /woocommerce-mobile-app.',
    ),
  ),
);
