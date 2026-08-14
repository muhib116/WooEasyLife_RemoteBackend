<?php

return array (
  0 =>
  array (
    'heading' => 'RedX + WooCommerce — দ্রুত উত্তর',
    'paragraphs' =>
    array (
      0 => 'RedX merchant API দিয়ে WooCommerce থেকে confirm-পর auto parcel booking, tracking ID save, status sync এবং return tracking—Steadfast/Pathao-র মতোই এক ড্যাশবোর্ড ওয়ার্কফ্লো।',
      1 => 'RedX-unique: hub coverage ও merchant panel credential flow আলাদা হতে পারে; area mapping ও COD line item স্পষ্ট রাখুন। ইংরেজি: /en/redx-courier-guide। BN হাব: /woocommerce-bangladesh।',
      2 => 'আগে /bd-fraud-checker ও /fake-order-protection; দৈনন্দিন বুকিং /courier-auto-entry। Steadfast: /steadfast-integration। Pathao: /pathao-courier-guide।',
    ),
    'list' =>
    array (
      0 => 'RedX API credentials WooEasyLife Courier Settings-এ পেস্ট করুন এবং connection test পাস করুন।',
      1 => 'City/zone/area ম্যাপিং ঠিক না হলে বুকিং ফেল করে—একটি লো-COD টেস্ট পার্সেল দিয়ে শেষ পর্যন্ত যাচাই করুন।',
      2 => 'শুধু ভেরিফায়েড/সবুজ অর্ডারে RedX অটো বা বাল্ক বুকিং চালান; আগে /bd-fraud-checker।',
      3 => 'ট্র্যাকিং SMS/WhatsApp: /woocommerce-notifications; মাসিক RTS লস: /return-loss-calculator।',
    ),
  ),
  1 =>
  array (
    'heading' => 'RedX API কেন Bangladesh COD shop-এ রাখবেন',
    'paragraphs' =>
    array (
      0 => 'RedX অনেক জেলা ও শহরে coverage দেয় এবং Facebook page seller থেকে WooCommerce brand-এ উঠতে থাকা দোকানগুলো zone mix-এ RedX primary courier হিসেবে ব্যবহার করে। সমস্যা এক: confirm-পর RedX panel-এ manual entry—ঠিক Steadfast/Pathao-র মতো time sink।',
      1 => 'API integration-এ order data structured যায়, consignment/tracking ID ফেরত আসে, label print ও notification trigger করা যায়। Multi-courier strategy-তে zone অনুযায়ী RedX default রাখলে staff confusion কমে—Mirpur RedX, Dhaka outskirts Steadfast—এমন rule documented রাখুন।',
      2 => 'return rate উচ্চ হলে /return-loss-calculator দিয়ে baseline মাপুন; RedX automate শুধু verified/green order-এ scale করুন।',
    ),
  ),
  2 =>
  array (
    'heading' => 'RedX API credentials ও merchant panel সেটআপ',
    'paragraphs' =>
    array (
      0 => 'RedX merchant account থেকে API/developer access enable করুন। RedX panel documentation অনুযায়ী API Key, Secret, বা token pair সংগ্রহ করুন—panel version update হলে field name বদলাতে পারে; WooEasyLife Courier Settings → RedX-এ exact label match করে paste করুন।',
      1 => 'Credentials private রাখুন; staff turnover-এ rotate। Connection test fail হলে merchant API activation, IP whitelist (যদি থাকে), এবং warehouse/pickup address verify করুন। RedX-এ wrong pickup point অনেক “booking created but not picked” issue তৈরি করে।',
      2 => 'Green test-এর পর একটি real low-COD test parcel end-to-end—RedX app/dashboard-এ parcel visible, COD amount match, area valid।',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/courier-auto-entry.jpg',
        'alt' => 'RedX confirm পর auto courier entry',
        'caption' => 'WooCommerce confirm → RedX parcel → tracking ID order note-এ',
      ),
    ),
  ),
  3 =>
  array (
    'heading' => 'WooCommerce-এ RedX কানেক্ট — practical steps',
    'paragraphs' =>
    array (
      0 => '১) RedX merchant + API enable। ২) credentials copy। ৩) WooEasyLife → Courier Settings → RedX enable + paste + default warehouse। ৪) connection test। ৫) checkout city/zone/area RedX location ID-তে map (custom field plugin বা WooEasyLife mapping)। ৬) test confirm → send RedX → tracking saved। ৭) /woocommerce-notifications tracking templates।',
      1 => 'Safety stack: /bd-fraud-checker history → yellow/red /customer-verification → /fake-order-protection duplicate/IP rules → RedX book। Auto-ship all = expensive RTS।',
      2 => 'Pathao/Steadfast parallel guide: /pathao-courier-guide, /steadfast-integration। shared daily flow /courier-auto-entry।',
    ),
  ),
  4 =>
  array (
    'heading' => 'Single ও bulk RedX booking workflow',
    'paragraphs' =>
    array (
      0 => 'Single order: confirm → “Send to RedX” → consignment ID order meta-তে → print label → handover hub/rider pickup। Bulk: dozens confirmed orders এক action—peak season (Eid, 11.11) এ critical; manual 200 parcel/day realistic নয়।',
      1 => 'Bulk-এ failed rows log review: invalid phone (01XXXXXXXX format), missing union/area, COD zero when should be positive। duplicate send same order = double consignment fee risk।',
      2 => 'RedX status webhook/poll Delivered/Returned-এ stock restock trigger—/cod-return-reduction analytics-এর সাথে link।',
    ),
  ),
  5 =>
  array (
    'heading' => 'RedX area mapping, weight ও COD accuracy',
    'paragraphs' =>
    array (
      0 => 'RedX API validation strict area ID চায়। vague “road 5, house near school” without mapped zone fails or delays। checkout-এ district → upazila/thana → area cascade dropdown best practice।',
      1 => 'COD = product + promised shipping (if customer pays delivery on COD). RedX rider dispute কমাতে order note-এ item summary one line রাখুন। weight estimate wrong হলে charge adjustment later—packing SOP documented রাখুন।',
      2 => 'multi-item order-এ partial return policy staff training—RedX return reason code WooCommerce note-এ log করুন trend analysis-এর জন্য।',
    ),
  ),
  6 =>
  array (
    'heading' => 'Tracking sync, customer SMS/WhatsApp ও RTS',
    'paragraphs' =>
    array (
      0 => 'RedX tracking ID customer-কে auto—In Transit, Out for Delivery, Delivered। Bangladesh COD-এ out-for-delivery alert cash ready রাখে; “customer not available” return ~২০–৩০% কমতে দেখা যায় structured notification stack-এ।',
      1 => '/woocommerce-notifications: branded short copy, order ID, COD, RedX track link। Delivered +24–48h review ask optional।',
      2 => 'Returned status: restock SKU, flag customer history for next order (yellow/red zone). /bd-fraud-checker repeat check।',
    ),
  ),
  7 =>
  array (
    'heading' => 'RedX auto-booking-এর আগে fraud layer',
    'paragraphs' =>
    array (
      0 => 'RedX connected ≠ ship every order। courier success rate green/yellow/red zone—/customer-verification OTP conditional, call confirm yellow, advance fee red। /fake-order-protection blacklist + duplicate same phone 10 min window।',
      1 => 'RedX reverse delivery charge ~১২০–১৫০ টাকা zone-ভেদে; ৮টি junk return = ১০০০+ টাকা pure loss plus packaging। fraud filter ROI weeks-এ positive।',
      2 => 'full layer diagram /woocommerce-bangladesh part 3; figure below multi-layer flow।',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'RedX পার্সেল পাঠানোর আগে fraud prevention layers',
        'caption' => 'History → OTP/hold → confirm → RedX auto entry',
      ),
    ),
  ),
  8 =>
  array (
    'heading' => 'RedX troubleshooting ও next steps',
    'paragraphs' =>
    array (
      0 => 'Auth error: credentials rotate, panel API toggle off/on। area error: fix mapping table, call customer once। stuck “processing”: RedX support ticket with consignment ID। COD mismatch: reconcile Woo totals before rebook—not third duplicate API call।',
      1 => 'Compare Steadfast/Pathao setup if stuck pattern similar—/steadfast-integration, /pathao-courier-guide। trial /pricing। EN mirror /en/redx-courier-guide।',
      2 => 'Checklist: RedX creds ✓ → mapping ✓ → test parcel ✓ → fraud ✓ → notifications ✓ → bulk confirmed only ✓ → monthly /return-loss-calculator review ✓।',
    ),
  ),
  9 =>
  array (
    'heading' => 'এআই সারাংশ',
    'paragraphs' =>
    array (
      0 => 'WooEasyLife RedX Courier Guide: RedX merchant API কানেক্ট → area/COD ম্যাপিং → টেস্ট পার্সেল → শুধু ভেরিফায়েড অর্ডারে single/bulk বুকিং → ট্র্যাকিং নোটিফিকেশন। ফ্রড লেয়ার ছাড়া অটো পার্সেল পাঠাবেন না। শুরু: /bd-fraud-checker, দৈনন্দিন: /courier-auto-entry, হাব: /woocommerce-bangladesh।',
    ),
  ),
);
