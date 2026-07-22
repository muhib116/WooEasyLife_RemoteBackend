<?php

return array (
  0 =>
  array (
    'heading' => 'Pathao + WooCommerce — বাংলাদেশ COD স্টোর গাইড',
    'paragraphs' =>
    array (
      0 => 'Pathao বাংলাদেশের Facebook ও WooCommerce COD সেলারদের অন্যতম ব্যবহৃত কুরিয়ার। সমস্যা প্রায়ই “কোন কুরিয়ার” নয়—প্রতিটি কনফার্মের পর Pathao মার্চেন্ট প্যানেলে নাম, ফোন, ঠিকানা ও COD amount কপি-পেস্ট করা।',
      1 => 'এই গাইডে Pathao merchant/developer API WooEasyLife-এ কানেক্ট, warehouse ম্যাপ, single/bulk বুকিং, tracking ID সংরক্ষণ ও status sync—সব WooCommerce ড্যাশবোর্ড থেকেই। /bd-fraud-checker ও /fake-order-protection দিয়ে অটো-বুকিংয়ের আগে ঝুঁকি ফিল্টার করুন।',
      2 => 'ইংরেজি: /en/pathao-courier-guide। হাব: /woocommerce-bangladesh। দৈনন্দিন ফ্লো: /courier-auto-entry।',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/courier-auto-entry.jpg',
        'alt' => 'Pathao কনফার্ম পর অটো পার্সেল এন্ট্রি',
        'caption' => 'একবার কনফার্ম → Pathao পার্সেল → ট্র্যাকিং ID অর্ডারে সেভ',
      ),
    ),
  ),
  1 =>
  array (
    'heading' => 'Pathao API ইন্টিগ্রেশন কেন ম্যানুয়াল বুকিং থেকে এগিয়ে',
    'paragraphs' =>
    array (
      0 => 'ম্যানুয়াল Pathao বুকিং ভলিউমে ভেঙে পড়ে: স্টাফ অর্ডার খোলে, ফিল্ড কপি, Pathao ট্যাবে পেস্ট, consignment ID নোটে ফেরত লেখে। দিনে ৪০–১০০ COD অর্ডারে এই লুপ ঘণ্টা নষ্ট করে এবং ভুল zone, missing COD, duplicate entry তৈরি করে।',
      1 => 'API দিয়ে WooEasyLife কনফার্মের পর structured order data Pathao-তে পাঠায়; Pathao tracking/consignment ID ফেরত দেয় যা অর্ডারে সেভ হয়। সাধারণ লাভ: ~৯০% কম booking সময়, কম address typo, পরে পরিষ্কার COD reconciliation।',
      2 => 'রিটার্ন ইতিমধ্যে লাভ কামাচ্ছে? আগে /return-loss-calculator দিয়ে মাসিক লস মাপুন—তারপর verified অর্ডারে Pathao automate করুন। Steadfast/RedX: /steadfast-integration, /redx-courier-guide।',
    ),
  ),
  2 =>
  array (
    'heading' => 'Pathao credentials: Client ID, Secret, Username, Password',
    'paragraphs' =>
    array (
      0 => 'Pathao developer/merchant API-তে সাধারণত চারটি মান লাগে: Client ID, Client Secret, Username, Password—Steadfast-এর single API Key প্যাটার্ন থেকে আলাদা। Pathao merchant/developer panel থেকে সংগ্রহ করুন; কেউ ছাড়লে বা leak হলে rotate করুন।',
      1 => 'WooEasyLife → Courier Settings → Pathao-তে চারটি value paste, enable Pathao, connection test চালান। Default store/warehouse location সেট করুন—credentials ঠিক থাকলেও wrong store mapping booking error দেয়।',
      2 => 'Green connection test-এর পর single low-risk test order end-to-end বুক করুন; Pathao panel-এ COD ও area verify করুন। তারপর /courier-auto-entry দিয়ে routine auto entry চালু করুন।',
    ),
  ),
  3 =>
  array (
    'heading' => 'ধাপে ধাপে: Pathao WooCommerce-এ কানেক্ট',
    'paragraphs' =>
    array (
      0 => '১) Pathao merchant account ও Developer/API access enable। ২) Client ID, Secret, Username, Password কপি। ৩) WooEasyLife Courier Settings-এ Pathao enable + credentials। ৪) warehouse save। ৫) connection test। ৬) test order confirm → Pathao → tracking ID save verify। ৭) optional: /woocommerce-notifications-এ tracking SMS/WhatsApp।',
      1 => 'নিরাপদ ক্রম: /bd-fraud-checker → /fake-order-protection (OTP/duplicate) → Pathao booking on confirm। fraud filter ছাড়া auto-ship = ব্যয়বহুল return machine।',
      2 => 'multi-courier shop-এ zone অনুযায়ী primary courier ঠিক করুন যাতে স্টাফ ভুল panel না খোলে।',
    ),
  ),
  4 =>
  array (
    'heading' => 'কনফার্ম → Pathao অটো এন্ট্রি ওয়ার্কফ্লো',
    'paragraphs' =>
    array (
      0 => 'আদর্শ লুপ: অর্ডার → fraud/history check → হলুদ/লালে কল/OTP → confirmed → name, phone, address, item note, COD Pathao-তে → consignment ID → label → pickup। Excel upload বা দ্বিতীয় ট্যাবে টাইপ নয়।',
      1 => 'Bulk: orders list থেকে dozens confirmed select → এক action-এ Pathao। single stable হওয়ার পর bulk; failed row review করে duplicate consignment এড়ান।',
      2 => 'Incomplete recovery ও tracking alerts /woocommerce-notifications-এ। COD math: /cod-return-reduction।',
    ),
  ),
  5 =>
  array (
    'heading' => 'শহর, zone, area ম্যাপিং ও COD amount',
    'paragraphs' =>
    array (
      0 => 'Pathao booking quality address structure-এ নির্ভর করে। WooCommerce city/zone/area (বা custom checkout field) Pathao location ID-তে map করুন। “মিরপুরে মসজিদের পাশে”—zone/area ছাড়া API validation fail বা wrong hub।',
      1 => 'COD amount rider যা collect করবে তার সাথে match করতে হবে—delivery charge rule সহ। mismatch = dispute ও reconciliation ঝামেলা। confirm-এর আগে cart consistent রাখুন।',
      2 => 'mixed SKU-তে packing note রাখুন; label ও Pathao entry align থাকে।',
    ),
  ),
  6 =>
  array (
    'heading' => 'Tracking, status sync ও কাস্টমার মেসেজ',
    'paragraphs' =>
    array (
      0 => 'Booking-এর পর Pathao tracking ID অর্ডারে save। In Transit ও Out for Delivery auto message-এ “পার্সেল কোথায়?” support load কমায়। Out-for-delivery-এ cash ready + rider call expected—customer unavailable return কমে।',
      1 => '/woocommerce-notifications-এ WhatsApp primary, SMS fallback; order ID, COD, tracking link। optional branded track page আপনার domain-এ।',
      2 => 'Pathao return/cancel হলে stock restock ও RTS flag—/cod-return-reduction playbook-এর সাথে মিলিয়ে month-end surprise এড়ান।',
    ),
  ),
  7 =>
  array (
    'heading' => 'অটো-বুক Pathao-র আগে ফ্রড চেক',
    'paragraphs' =>
    array (
      0 => 'Pathao automation confirmation-এর পর শক্তিশালী—verification replace করে না। /bd-fraud-checker-এ mobile success rate: সবুজ দ্রুত; হলুদ কল/OTP; লাল advance বা hold—/customer-verification, /fake-order-protection।',
      1 => '১০ high-risk COD “Pathao connected” বলে ship = reverse fee + packaging + ad CPA দিয়ে profit erase। clean order-এ automate; risky-তে slow।',
      2 => 'return টাকা /return-loss-calculator; ad spend বাড়ানোর আগে RTS control।',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'Pathao বুকিংয়ের আগে ফেক অর্ডার প্রটেকশন',
        'caption' => 'হিস্টোরি + OTP + কনফার্ম — তারপর Pathao অটো এন্ট্রি',
      ),
    ),
  ),
  8 =>
  array (
    'heading' => 'Pathao API troubleshooting ও পরবর্তী ধাপ',
    'paragraphs' =>
    array (
      0 => 'Connection fail: Client ID/Secret/username/password re-copy, API access enabled, extra space check। password change = WooEasyLife update immediately। area fail = mapping বা customer থেকে clear address। duplicate = existing tracking ID check। COD mismatch = cart reconcile once rebook।',
      1 => 'Steadfast pattern: /steadfast-integration। RedX: /redx-courier-guide। ops: /courier-auto-entry। production: /pricing trial।',
      2 => 'চেকলিস্ট: credentials ✓ → test green ✓ → store mapped ✓ → test parcel ✓ → fraud on ✓ → notifications ✓ → bulk confirmed only ✓। EN: /en/pathao-courier-guide।',
    ),
  ),
);
