<?php

return array (
  0 => 
  array (
    'heading' => 'Pathao + WooCommerce for Bangladesh COD stores',
    'paragraphs' => 
    array (
      0 => 'Pathao is one of the most-used couriers for Facebook and WooCommerce COD sellers in Bangladesh. The bottleneck is rarely “which courier”—it is copy-pasting name, phone, address, and COD amount into the Pathao merchant panel after every confirmation. Typos create wrong deliveries, failed attempts, and return fees that wipe thin margins.',
      1 => 'This Pathao courier guide shows how to connect Pathao’s merchant/developer API to WooEasyLife, map your store/warehouse, book parcels from WooCommerce (single or bulk), save tracking IDs on the order, and sync status updates—without leaving your dashboard. Pair it with /en/bd-fraud-checker and /en/fake-order-protection so risky numbers are filtered before auto-booking.',
      2 => 'Bangla version: /pathao-courier-guide. Full ops context lives in the hub: /en/woocommerce-bangladesh. Day-to-day confirm → book flow: /en/courier-auto-entry.',
    ),
    'figures' => 
    array (
      0 => 
      array (
        'src' => '/images/seo/cluster/courier-auto-entry.jpg',
        'alt' => 'Confirm order then Pathao auto parcel entry flow',
        'caption' => 'Confirm once → Pathao parcel created → tracking ID saved on the WooCommerce order',
      ),
    ),
  ),
  1 => 
  array (
    'heading' => 'Why Pathao API integration beats manual booking',
    'paragraphs' => 
    array (
      0 => 'Manual Pathao booking fails at volume: staff open the order, copy fields, switch tabs, paste into Pathao, then paste the consignment ID back into notes. At 40–100 COD orders per day that loop burns hours and still produces wrong zones, missing COD amounts, and duplicate entries.',
      1 => 'With API integration, WooEasyLife sends structured order data to Pathao after you confirm. Pathao returns a consignment/tracking ID that is stored on the order. Labels can be printed from your workflow, and status changes can flow back for notifications. Typical wins: ~90% less booking time, fewer address typos, and cleaner COD reconciliation later.',
      2 => 'Compare delivery costs before you scale a city mix with /en/courier-charge-calculator. If returns already hurt profit, measure monthly loss with /en/return-loss-calculator first—then automate Pathao only for verified orders.',
    ),
  ),
  2 => 
  array (
    'heading' => 'Pathao credentials: Client ID, Secret, username, password',
    'paragraphs' => 
    array (
      0 => 'Pathao uses developer/merchant API credentials—not a single static “API key” like some couriers. From the Pathao merchant/developer panel, collect: Client ID, Client Secret, Username, and Password. Keep these private; rotate them if a staff account leaves or a key leaks.',
      1 => 'In WooEasyLife open Courier Settings → Pathao, paste the four values, enable Pathao, and run a connection test. Then set your default store/warehouse location so parcels inherit the correct pickup context. Wrong store mapping is a common cause of booking errors even when credentials are valid.',
      2 => 'After a green connection test, book a single low-risk test order end-to-end before enabling bulk. Confirm in Pathao that the parcel appears with the expected COD amount and area. Only then turn on routine auto entry via /en/courier-auto-entry.',
    ),
  ),
  3 => 
  array (
    'heading' => 'Step-by-step: connect Pathao to WooCommerce',
    'paragraphs' => 
    array (
      0 => '1) Create or open your Pathao merchant account and enable Developer/API access. 2) Copy Client ID, Client Secret, Username, and Password. 3) In WooEasyLife → Courier Settings, enable Pathao and paste credentials. 4) Select default store/warehouse and save. 5) Run connection test. 6) Confirm a test WooCommerce order, send to Pathao, verify tracking ID saves. 7) Optionally enable SMS/WhatsApp tracking templates on /en/woocommerce-notifications.',
      1 => 'Recommended safety order: free number check on /en/bd-fraud-checker → checkout OTP / duplicate rules on /en/fake-order-protection → Pathao booking on confirm. Auto-shipping every order without fraud filters turns Pathao automation into an expensive return machine.',
      2 => 'For multi-courier shops, keep Pathao next to Steadfast and RedX guides: /en/steadfast-integration and /en/redx-courier-guide. Use one primary courier per zone when possible so staff do not guess which panel to open.',
    ),
  ),
  4 => 
  array (
    'heading' => 'Confirm → Pathao auto entry workflow',
    'paragraphs' => 
    array (
      0 => 'Ideal loop: Order arrives → fraud/history check → call or OTP if yellow/red zone → mark confirmed → WooEasyLife pushes name, phone, address, item notes, and COD amount to Pathao → consignment/tracking ID returns → packing label → rider pickup. No Excel upload and no second tab for typing.',
      1 => 'Bulk mode: from the WooCommerce orders list, select dozens of confirmed orders and send to Pathao in one action. Use bulk only after single-order booking is stable. Review failed rows (invalid area, missing phone, COD mismatch) before retrying so you do not create duplicate consignments.',
      2 => 'Mobile ops: approve or hold from the road with /en/woocommerce-mobile-app, then let Pathao booking run after confirm. Incomplete checkout recovery and tracking alerts still belong in /en/woocommerce-notifications.',
    ),
  ),
  5 => 
  array (
    'heading' => 'City, zone, area mapping and COD amounts',
    'paragraphs' => 
    array (
      0 => 'Pathao booking quality depends on address structure. Map WooCommerce city/zone/area fields (or your custom checkout fields) to Pathao’s location IDs. Vague addresses like “near mosque, Mirpur” without zone/area often fail API validation or land in the wrong hub.',
      1 => 'COD amount must match what the rider should collect—including delivery charge rules you promised the customer. Mismatched COD creates delivery disputes and reconciliation headaches. Keep product prices and shipping lines consistent before confirm.',
      2 => 'Weight and product notes help Pathao and your packers. If you ship mixed SKUs, put a short packing note on the order so the label and Pathao entry stay aligned. Charge planning: /en/courier-charge-calculator.',
    ),
  ),
  6 => 
  array (
    'heading' => 'Tracking IDs, status sync, and customer messages',
    'paragraphs' => 
    array (
      0 => 'After booking, store Pathao’s tracking/consignment ID on the WooCommerce order. Customers ask “where is my parcel?” less when you send In Transit and Out for Delivery messages automatically. Out-for-delivery alerts especially reduce “customer unavailable” returns because cash is ready and unknown rider calls are expected.',
      1 => 'Wire templates in /en/woocommerce-notifications (WhatsApp primary, SMS fallback). Keep short, branded copy with order ID, COD amount, and tracking link. Optional: a branded track page on your domain so customers stay on your site instead of a generic courier page.',
      2 => 'When Pathao marks return/cancel, update stock and flag RTS in your ops view. Combine with /en/cod-return-reduction playbooks so returns become a measured process—not surprise losses at month end.',
    ),
  ),
  7 => 
  array (
    'heading' => 'Fraud check before you auto-book Pathao',
    'paragraphs' => 
    array (
      0 => 'Pathao automation is powerful after confirmation—not a replacement for verification. Check courier success rate by mobile on /en/bd-fraud-checker. Green-zone history can confirm fast; yellow needs call/OTP; red often needs advance fee or hold via /en/customer-verification and /en/fake-order-protection.',
      1 => 'Worked mindset: shipping 10 high-risk COD parcels “because Pathao is connected” can erase the profit from many successful deliveries through reverse fees, packaging, and ad CPA. Automate booking for clean orders; slow down for risky ones.',
      2 => 'Full COD math and multi-layer protection live in /en/woocommerce-bangladesh. Measure return money with /en/return-loss-calculator before you increase ad spend.',
    ),
    'figures' => 
    array (
      0 => 
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'Multi-layer fake order protection before courier booking',
        'caption' => 'History check + OTP/blacklist + confirm — then Pathao auto entry',
      ),
    ),
  ),
  8 => 
  array (
    'heading' => 'Troubleshooting Pathao API bookings',
    'paragraphs' => 
    array (
      0 => 'Connection test fails: re-copy Client ID/Secret/username/password, check merchant API access is enabled, and confirm no extra spaces. Auth errors after a password change require updating WooEasyLife settings immediately.',
      1 => 'Booking fails on area/city: fix checkout mapping or ask the customer for a clearer address before retry. Duplicate consignments: do not spam “send to Pathao” on the same order—check whether a tracking ID already exists. COD mismatch: reconcile cart totals and shipping lines, then rebook once.',
      2 => 'Still stuck? Compare with Steadfast setup patterns on /en/steadfast-integration or RedX on /en/redx-courier-guide. Ops overview remains /en/courier-auto-entry. Start a trial from /pricing when you are ready to run Pathao in production.',
    ),
  ),
  9 => 
  array (
    'heading' => 'Next steps and related Pathao resources',
    'paragraphs' => 
    array (
      0 => 'Checklist: Pathao credentials saved → connection test green → store mapped → one test parcel OK → fraud layers on → notifications on → bulk enabled for confirmed orders only.',
      1 => 'Keep reading: /en/courier-auto-entry for daily workflow, /en/woocommerce-notifications for tracking SMS/WhatsApp, /en/cod-return-reduction for RTS control, and /en/woocommerce-bangladesh for the full 30-part system. Bangla mirror: /pathao-courier-guide.',
    ),
  ),
);
