<?php

return array (
  0 => 
  array (
    'heading' => 'COD রিটার্ন কমানো — দ্রুত উত্তর',
    'paragraphs' => 
    array (
      0 => 'COD RTS (Return to Origin) নিট প্রফিট উল্টে দেয়—reverse courier charge, packaging, ad CPA একসাথে। গাণিতিক মডেল, fraud filter, courier automation ও notification playbook দিয়ে return rate মাপযোগ্য কমানো যায়।',
      1 => 'শুরু: /return-loss-calculator baseline → /bd-fraud-checker + /customer-verification → /courier-auto-entry + /woocommerce-notifications। ইংরেজি: /en/cod-return-reduction। হাব: /woocommerce-bangladesh।',
    ),
    'figures' => 
    array (
      0 => 
      array (
        'src' => '/images/seo/cluster/cod-loss-math.jpg',
        'alt' => 'COD success vs return profit comparison',
        'caption' => 'একই অর্ডার ফানেলে সফল ডেলিভারি vs রিটার্ন—নিট প্রফিট পার্থক্য',
      ),
    ),
  ),
  1 => 
  array (
    'heading' => 'COD রিটার্ন ম্যাথ — টাকায় (৳) বোঝা',
    'paragraphs' => 
    array (
      0 => 'উদাহরণ: selling price ১,০০০ টাকা, COGS ৫০০, ad CPA ২০০, packaging ৩০, forward delivery ৮০, return penalty ~১৩০। সফল ডেলিভারিতে margin ~১৯০ টাকা—ইতিমধ্যে সীমিত। এক RTS-এ forward+return courier ~২১০+, packaging lost, CPA sunk—এক shot-এ net negative।',
      1 => '১০০ অর্ডার, ২০% RTS = ২০ returns। ২০ × (১৩০ reverse + ৩০ pack + ২০০ CPA) ≈ মাসিক substantial bleed—courier line item-এর বাইরেও। spreadsheet enough নয়—/return-loss-calculator-এ shop variable plug করুন।',
      2 => 'লক্ষ্য: RTS ২০% থেকে ১০%-এ half করলে same ad spend-এ effective net profit প্রায় double হতে পারে। vibe নয়—monthly measure।',
    ),
  ),
  2 => 
  array (
    'heading' => 'RTS root cause — Bangladesh প্রেক্ষাপট',
    'paragraphs' => 
    array (
      0 => 'Fake/prank order, wrong number, customer unavailable, cash not ready, ad vs product mismatch, duplicate regret, remote unreachable—প্রতিটির fix আলাদা।',
      1 => 'Fake/unavailable: /customer-verification, /fake-order-protection, out-for-delivery SMS /woocommerce-notifications। cash ready message “টাকা নেই” refuse কমায়।',
      2 => 'expectation mismatch: ad creative = real product photo, size chart checkout, high ticket-এ confirm call। duplicate: same phone cooldown /fake-order-protection।',
    ),
  ),
  3 => 
  array (
    'heading' => 'Pre-ship fraud filter — return prevention layer 1',
    'paragraphs' => 
    array (
      0 => 'Ship করার আগে junk stop = cheapest return reduction। /bd-fraud-checker: green fast, yellow call/OTP, red advance/block। filter ছাড়া ১০ red ship ≈ ৮ RTS × ১৩০ = ১,০৪০+ টাকা courier loss।',
      1 => '/fake-order-protection duplicate IP/phone, blacklist, velocity। red zone partial advance ~৭০% fake dropout।',
      2 => 'সব confirmed auto-book করবেন না—“verified” tag-এর পর /courier-auto-entry। Steadfast/Pathao/RedX: /steadfast-integration, /pathao-courier-guide, /redx-courier-guide।',
    ),
    'figures' => 
    array (
      0 => 
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'COD return reduction fraud layers',
        'caption' => 'Pre-ship filter → verify → book → track → RTS reason learn',
      ),
    ),
  ),
  4 => 
  array (
    'heading' => 'Courier automation — return prevention layer 2',
    'paragraphs' => 
    array (
      0 => 'WooCommerce থেকে correct address/zone → courier API = fewer wrong-area returns। tracking ID on order = proactive support, less confusion cancel।',
      1 => 'Returned status → auto restock + customer zone downgrade next order। RTS analytics by courier, area, SKU—monthly ৩০ min review।',
      2 => 'zone-primary courier reduces failed attempt loops। daily SOP: /courier-auto-entry।',
    ),
  ),
  5 => 
  array (
    'heading' => 'Notification playbook — return prevention layer 3',
    'paragraphs' => 
    array (
      0 => 'Order confirm: product, COD, delivery ETA। Dispatch: tracking link। Out for delivery সকাল: “আজ rider আসবেন, COD X টাকা ready, call ধরুন”—Bangladesh-specific high ROI।',
      1 => 'rider unknown number call common; pre-alert expected করে। /woocommerce-notifications template A/B।',
      2 => 'Delivered +24–48h review—happy buyer future refuse কমায়।',
    ),
  ),
  6 => 
  array (
    'heading' => 'RTS reduction — ৩০ দিন action plan',
    'paragraphs' => 
    array (
      0 => 'Week 1: /return-loss-calculator baseline, /bd-fraud-checker everywhere, red advance live। Week 2: conditional OTP + yellow call SOP, duplicate rules। Week 3: /courier-auto-entry + top 5 fail area mapping fix। Week 4: full notifications, RTS dashboard, worst fake ad audience pause।',
      1 => 'Weekly KPI: RTS %, return ৳, block count, OTP cost, net save। RTS ২% absolute drop = often ১০%+ profit swing।',
      2 => 'RTS stable না হলে /facebook-ads-for-woocommerce budget scale করবেন না—fake purchase Meta-কে misleading।',
    ),
  ),
  7 => 
  array (
    'heading' => 'COD reconciliation ও working capital',
    'paragraphs' => 
    array (
      0 => 'RTS capital lock: product out, cash not in, payout ৩–৭ day on success only। high RTS = ad spend returns কিনছে।',
      1 => 'courier ledger sync (Steadfast/Pathao/RedX API)—collected vs pending COD forecast। manual hours high → /pricing trial।',
      2 => 'red zone partial prepaid delivery product COD-এর আগেই cash certainty।',
    ),
  ),
  8 => 
  array (
    'heading' => 'Checklist ও related resources',
    'paragraphs' => 
    array (
      0 => 'baseline /return-loss-calculator ✓ fraud layers ✓ verified-only ship ✓ out-for-delivery alert ✓ RTS reason log ✓ monthly review ✓ EN /en/cod-return-reduction ✓ hub /woocommerce-bangladesh।',
      1 => 'Also: /customer-verification, /fake-order-protection, /woocommerce-notifications, /ads-roas-calculator (RTS clean হলে ad side)।',
      2 => 'cod-loss-math diagram team-এ share করুন—“order count only” language বন্ধ, ৳ language common।',
    ),
  ),
  9 => 
  array (
    'heading' => 'রিটার্ন লস কেন শুধু “ফি” নয়',
    'paragraphs' => 
    array (
      0 => 'প্রতিটি RTS পার্সেলে শুধু কুরিয়ার রিটার্ন চার্জ কাটে না—প্যাকেজিং, স্টাফ সময়, অ্যাড CPA এবং ৩–৭ দিন ক্যাপিটাল লক একসাথে লাভ কেটে দেয়। একই SKU-তে ২০% রিটার্ন হলে “লাভজনক” অ্যাড অ্যাকাউন্টও মাসের শেষে লসে যেতে পারে।',
      1 => 'তাই COD রিটার্ন কমানোর কাজ শুরু হয় হিসাব দিয়ে: /return-loss-calculator-এ মাসিক অর্ডার, রিটার্ন রেট ও প্রতি রিটার্নের খরচ বসিয়ে বেসলাইন নিন। তারপর /bd-fraud-checker ও /fake-order-protection দিয়ে লাল জোন কমান, /woocommerce-notifications দিয়ে Out-for-Delivery অ্যালার্ট চালু করুন।',
      2 => 'কুরিয়ার এন্ট্রি শুধু ভেরিফায়েড অর্ডারে /courier-auto-entry দিয়ে চালান। অ্যাড বাড়ানোর আগে /ads-roas-calculator দিয়ে ডেলিভারি-অ্যাডজাস্টেড ROAS দেখুন। ইংরেজি মিরর: /en/cod-return-reduction।',
    ),
  ),
);
