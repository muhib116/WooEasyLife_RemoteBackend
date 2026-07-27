<?php

return array (
  0 =>
  array (
    'heading' => 'Steadfast + WooCommerce for Bangladesh COD stores',
    'paragraphs' =>
    array (
      0 => 'Steadfast is a go-to courier for WooCommerce and Facebook COD sellers across Dhaka, Chittagong, and district routes. The daily pain is not choosing Steadfast—it is retyping customer name, phone, address, and COD amount into the Steadfast merchant panel after every confirmation. One wrong digit costs a failed delivery, reverse charge, and wasted packing time.',
      1 => 'This Steadfast integration guide walks through connecting Steadfast’s merchant API to WooEasyLife, mapping your warehouse, booking parcels from WooCommerce (single or bulk), saving consignment IDs on orders, and syncing status back for customer alerts. Layer /en/bd-fraud-checker and /en/fake-order-protection first so risky numbers never auto-book.',
      2 => 'Bangla version: /steadfast-integration. Full system context: /en/woocommerce-bangladesh. Day-to-day confirm → book workflow: /en/courier-auto-entry.',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/courier-auto-entry.jpg',
        'alt' => 'Confirm order then Steadfast auto parcel entry flow',
        'caption' => 'Confirm once → Steadfast consignment created → tracking ID saved on the WooCommerce order',
      ),
    ),
  ),
  1 =>
  array (
    'heading' => 'Why Steadfast API integration beats manual panel booking',
    'paragraphs' =>
    array (
      0 => 'Manual Steadfast booking breaks at scale: open WooCommerce, copy five fields, switch to Steadfast, paste, wait for consignment ID, paste back into order notes. At 50–120 COD orders per day that loop eats hours and still produces wrong areas, missing COD totals, and duplicate consignments when staff retry blindly.',
      1 => 'With API integration, WooEasyLife pushes structured order data to Steadfast the moment you confirm. Steadfast returns a consignment/tracking ID stored on the order. Labels print from your workflow; status webhooks feed notifications. Typical wins: ~90% less booking time, fewer address typos, and cleaner month-end COD reconciliation.',
      2 => 'Before you scale volume, compare route costs with /en/courier-charge-calculator. If returns already sting, quantify monthly loss on /en/return-loss-calculator—then automate Steadfast only for verified orders.',
    ),
  ),
  2 =>
  array (
    'heading' => 'Steadfast credentials: API Key and Secret from merchant Settings',
    'paragraphs' =>
    array (
      0 => 'Steadfast uses a merchant API Key + Secret pair—not OAuth tokens like some platforms. Log into the Steadfast merchant panel, open Settings → API Integration, and copy both values. Treat them like passwords: restrict access, rotate if staff leave, and never paste into public chats or screenshots.',
      1 => 'In WooEasyLife go to Courier Settings → Steadfast, paste API Key and Secret, enable Steadfast, and run a connection test. Then map your default store/warehouse so pickup context matches where riders actually collect. Wrong warehouse mapping is the top cause of “booking failed” even when credentials are valid.',
      2 => 'After a green test, book one low-risk test order end-to-end before turning on bulk. Confirm in Steadfast that COD amount and area match what the customer agreed. Only then enable routine auto entry via /en/courier-auto-entry.',
    ),
  ),
  3 =>
  array (
    'heading' => 'Step-by-step: connect Steadfast to WooCommerce',
    'paragraphs' =>
    array (
      0 => '1) Open or create your Steadfast merchant account and confirm API access is enabled. 2) Copy API Key and Secret from Settings → API Integration. 3) In WooEasyLife → Courier Settings, enable Steadfast and paste both values. 4) Select default warehouse/store location and save. 5) Run connection test until green. 6) Confirm a test WooCommerce order, send to Steadfast, verify consignment ID saves on the order. 7) Optionally wire tracking templates on /en/woocommerce-notifications.',
      1 => 'Recommended safety stack: free number check on /en/bd-fraud-checker → checkout OTP and duplicate rules on /en/fake-order-protection → Steadfast booking on confirm. Auto-shipping every order without fraud filters turns Steadfast automation into an expensive return machine.',
      2 => 'Multi-courier shops: keep Steadfast alongside /en/pathao-courier-guide and /en/redx-courier-guide. Assign one primary courier per zone when possible so staff never guess which panel to open.',
    ),
  ),
  4 =>
  array (
    'heading' => 'Confirm → Steadfast auto entry and bulk booking',
    'paragraphs' =>
    array (
      0 => 'Ideal loop: Order arrives → fraud/history check → call or OTP if yellow/red zone → mark confirmed → WooEasyLife sends name, phone, address, item notes, and COD amount to Steadfast → consignment ID returns → print label → rider pickup. No Excel export and no second browser tab.',
      1 => 'Bulk mode: from the WooCommerce orders list, select dozens of confirmed orders and send to Steadfast in one action. Use bulk only after single-order booking is stable. Review failed rows—invalid area, missing phone, COD mismatch—before retrying so you do not create duplicate consignments.',
      2 => 'Mobile ops: approve or hold from the road with /en/woocommerce-mobile-app, then let Steadfast booking run after confirm. Incomplete checkout recovery and tracking alerts still belong in /en/woocommerce-notifications.',
    ),
  ),
  5 =>
  array (
    'heading' => 'Tracking IDs, status sync, and customer messages',
    'paragraphs' =>
    array (
      0 => 'After booking, Steadfast’s consignment/tracking ID lives on the WooCommerce order. Customers ask “where is my parcel?” less when you send In Transit and Out for Delivery messages automatically. Out-for-delivery alerts especially cut “customer unavailable” returns because cash is ready and the rider call is expected.',
      1 => 'Configure templates in /en/woocommerce-notifications (WhatsApp primary, SMS fallback). Keep copy short: order ID, COD amount, tracking link. Optional branded track page on your domain keeps customers on your site instead of a generic courier page.',
      2 => 'When Steadfast marks return or cancel, update stock and flag RTS in your ops view. Pair with /en/cod-return-reduction playbooks so returns become a measured process—not surprise losses at month end.',
    ),
  ),
  6 =>
  array (
    'heading' => 'Fraud check before you auto-book Steadfast',
    'paragraphs' =>
    array (
      0 => 'Steadfast automation is powerful after confirmation—not a replacement for verification. Check courier success rate by mobile on /en/bd-fraud-checker. Green-zone history can confirm fast; yellow needs call/OTP; red often needs advance fee or hold via /en/fake-order-protection.',
      1 => 'Worked mindset: shipping 10 high-risk COD parcels “because Steadfast is connected” can erase profit from many successful deliveries through reverse fees, packaging, and ad CPA. Automate booking for clean orders; slow down for risky ones.',
      2 => 'Full COD math and multi-layer protection live in /en/woocommerce-bangladesh. Measure return money with /en/return-loss-calculator before you increase ad spend.',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'Multi-layer fake order protection before Steadfast courier booking',
        'caption' => 'History check + OTP/blacklist + confirm — then Steadfast auto entry',
      ),
    ),
  ),
  7 =>
  array (
    'heading' => 'SteadFast Return Requests — ask to return and Decide',
    'paragraphs' =>
    array (
      0 => 'The official SteadFast WordPress plugin focuses on booking, tracking, and balance. Developer SDKs expose return-request APIs, but Bangladesh merchants need a WP workflow: Ask to return → Pending list → Confirm cancel or Resend—without living in the SteadFast portal. WooEasyLife’s Courier hub ships that Decide flow for SteadFast (package: courier_automation; portal login required).',
      1 => 'Statuses: pending → confirmed / resend_request / cancelled / resent. Create from the order, sync into the hub, then Decide on pending rows. CSV export and refresh keep the list aligned with the portal.',
      2 => 'Pathao/RedX do not have this Return Requests hub yet—keep their booking/webhooks on /en/pathao-courier-guide and /en/redx-courier-guide. Measure return cost on /en/return-loss-calculator.',
    ),
  ),
  8 =>
  array (
    'heading' => 'Courier Notifications and stuck-parcel scan',
    'paragraphs' =>
    array (
      0 => 'SteadFast portal delivery notes, rider messages, and cancellation requests cache into the Notifications tab so staff do not tab-hop for every update. Open SteadFast jumps to the portal when needed.',
      1 => 'Stuck scan flags consignments with no note/update for ~3 days (configurable), deep-links the WooCommerce order, and supports Decide/notes from the same surface. Hourly cron plus manual Scan stuck.',
      2 => 'Portal notification sync is SteadFast-only today. Parcel notes history and rider callbacks sit in the same ops stack. Daily booking loop: /en/courier-auto-entry.',
    ),
  ),
  9 =>
  array (
    'heading' => 'Troubleshooting Steadfast API bookings',
    'paragraphs' =>
    array (
      0 => 'Connection test fails: re-copy API Key and Secret from Settings → API Integration, confirm API access is enabled on the merchant account, and check for trailing spaces. Auth errors after a key rotation require updating WooEasyLife settings immediately.',
      1 => 'Return/Notifications empty: save SteadFast portal email/password in Courier Settings (API keys alone are not enough for scrape features). Booking fails on area/city: fix checkout mapping. Duplicate consignments: do not spam send on the same order.',
      2 => 'Still stuck? Compare Pathao on /en/pathao-courier-guide or RedX on /en/redx-courier-guide. Ops overview: /en/courier-auto-entry. Start a trial from /pricing.',
    ),
  ),
  10 =>
  array (
    'heading' => 'Next steps and related Steadfast resources',
    'paragraphs' =>
    array (
      0 => 'Checklist: SteadFast API Key + Secret → portal login → connection test green → warehouse mapped → test parcel OK → fraud layers on → one Return Request Decide test → Notifications refresh → bulk for confirmed orders only.',
      1 => 'Keep reading: /en/courier-auto-entry, /en/woocommerce-notifications, /en/cod-return-reduction, /en/woocommerce-bangladesh. Bangla mirror: /steadfast-integration.',
    ),
  ),
);
