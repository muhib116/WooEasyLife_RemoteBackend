<?php

return array (
  0 =>
  array (
    'heading' => 'Steadfast + WooCommerce — দ্রুত উত্তর',
    'paragraphs' =>
    array (
      0 => 'Steadfast কুরিয়ার API দিয়ে WooCommerce থেকে এক-ক্লিক বা বাল্ক পার্সেল বুকিং, ট্র্যাকিং আইডি সংরক্ষণ, স্ট্যাটাস সিঙ্ক এবং রিটার্ন ট্র্যাকিং করা যায়—ম্যানুয়াল প্যানেলে কপি-পেস্ট ছাড়াই।',
      1 => 'সঠিক ক্রম: আগে /bd-fraud-checker ও /fake-order-protection চালু রাখুন, তারপর কনফার্মের পর /courier-auto-entry ওয়ার্কফ্লো দিয়ে Steadfast-এ অটো এন্ট্রি। ইংরেজি সংস্করণ: /en/steadfast-integration। পূর্ণ সিস্টেম: /woocommerce-bangladesh।',
    ),
  ),
  1 =>
  array (
    'heading' => 'কেন Steadfast API ইন্টিগ্রেশন ম্যানুয়াল বুকিং থেকে ভালো',
    'paragraphs' =>
    array (
      0 => 'বাংলাদেশের Facebook ও WooCommerce COD সেলারদের দৈনন্দিন কাজের সবচেয়ে বড় সময় চুরি হয় Steadfast মার্চেন্ট প্যানেলে হাতে টাইপ করে পার্সেল এন্ট্রি দেওয়া। অর্ডার কনফার্ম → নাম, ফোন, ঠিকানা, COD অ্যামাউন্ট কপি → Steadfast ট্যাবে পেস্ট → কনসাইনমেন্ট ID আবার নোটে লিখে রাখা—এই লুপ দিনে ৫০–১০০ অর্ডারে ঘণ্টার পর ঘণ্টা খায়।',
      1 => 'ম্যানুয়াল প্রক্রিয়ায় টাইপো, ভুল জোন, COD মিসম্যাচ এবং ডুপ্লিকেট এন্ট্রি নিয়মিত ঘটে। Steadfast API ইন্টিগ্রেশনে WooCommerce অর্ডার থেকে স্ট্রাকচার্ড ডাটা সরাসরি Steadfast সার্ভারে যায়; ফেরত আসে ট্র্যাকিং/কনসাইনমেন্ট ID যা অর্ডারে সেভ হয়। ফলে প্যাকিং ভুল কমে, স্টাফ একই ড্যাশবোর্ডে থাকে, এবং COD রিকনসিলিয়েশন পরে সহজ হয়।',
      2 => 'আগে মাসিক রিটার্ন লস মাপুন /return-loss-calculator দিয়ে—Steadfast অটোমেশন শুধু তখনই লাভজনক যখন ঝুঁকিপূর্ণ অর্ডার আগে ফিল্টার হয়। Pathao ও RedX-এর পাশাপাশি Steadfast রাখতে চাইলে /pathao-courier-guide ও /redx-courier-guide দেখুন।',
    ),
  ),
  2 =>
  array (
    'heading' => 'Steadfast API Key ও Secret Key সংগ্রহ',
    'paragraphs' =>
    array (
      0 => 'Steadfast মার্চেন্ট API সাধারণত API Key এবং Secret Key জোড়া ব্যবহার করে—Pathao-র Client ID/Secret/Username/Password প্যাটার্ন থেকে আলাদা। Steadfast মার্চেন্ট প্যানেলে লগইন করে Settings → API Integration (বা Developer/API) অপশন থেকে দুটি কী কপি করুন।',
      1 => 'কীগুলো গোপন রাখুন; স্টাফ ছাড়লে বা লিক সন্দেহ হলে Steadfast প্যানেল থেকে রোটেট করুন এবং WooEasyLife Courier Settings-এ আপডেট করুন। Extra space বা ভুল character পেস্ট হলে connection test fail দেখায়—কপি করার পর একবার Notepad-এ paste করে trim করে নিন।',
      2 => 'API access চালু না থাকলে Steadfast সাপোর্ট বা অ্যাকাউন্ট ম্যানেজারের সাথে মার্চেন্ট API enable করান। টেস্ট অর্ডার দিয়ে end-to-end বুকিং যাচাই করার আগে production bulk চালু করবেন না।',
    ),
  ),
  3 =>
  array (
    'heading' => 'WooCommerce-এ Steadfast কানেক্ট — ধাপে ধাপে',
    'paragraphs' =>
    array (
      0 => '১) Steadfast মার্চেন্ট অ্যাকাউন্ট খুলে API Integration enable করুন। ২) API Key ও Secret Key কপি করুন। ৩) WooEasyLife → Courier Settings → Steadfast enable করে কী দুটি পেস্ট করুন। ৪) ডিফল্ট warehouse/store address সিলেক্ট করুন—ভুল pickup context অনেক booking error-এর কারণ। ৫) Connection test চালান। ৬) একটি লো-রিস্ক টেস্ট অর্ডার কনফার্ম করে Steadfast-এ পাঠান; প্যানেলে COD ও area ঠিক আছে কিনা দেখুন।',
      1 => 'সুরক্ষিত ক্রম: /bd-fraud-checker দিয়ে নম্বর চেক → হলুদ/লাল জোনে /customer-verification (কল/OTP) → কনফার্ম → /courier-auto-entry দিয়ে Steadfast বুকিং। প্রতিটি অর্ডার অটো-শিপ করলে Steadfast দ্রুতই ব্যয়বহুল RTS মেশিনে পরিণত হয়।',
      2 => 'ট্র্যাকিং মেসেজের জন্য /woocommerce-notifications সেটআপ করুন। ট্রায়াল শুরু: /pricing।',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/courier-auto-entry.jpg',
        'alt' => 'Steadfast কনফার্ম পর অটো পার্সেল এন্ট্রি ফ্লো',
        'caption' => 'কনফার্ম → Steadfast পার্সেল তৈরি → ট্র্যাকিং ID WooCommerce অর্ডারে সেভ',
      ),
    ),
  ),
  4 =>
  array (
    'heading' => 'এক-ক্লিক ও বাল্ক Steadfast পার্সেল বুকিং',
    'paragraphs' =>
    array (
      0 => 'API সেটআপ শেষ হলে WooCommerce Orders তালিকা থেকে একক অর্ডারে “Send to Steadfast” বা সমতুল্য অ্যাকশন দিয়ে তৎক্ষণাৎ বুকিং করা যায়। বাল্ক মোডে ২০–১০০টি কনফার্মড অর্ডার একসাথে সিলেক্ট করে এক অ্যাকশনে Steadfast-এ পাঠান—যেখানে ম্যানুয়ালি এক ঘণ্টা লাগত, API-তে কয়েক সেকেন্ড।',
      1 => 'বাল্ক চালু করার আগে single-order ফ্লো stable করুন। Failed row (invalid area, missing phone, COD mismatch) আলাদা দেখে retry করুন—একই অর্ডারে বারবার “send” চাপলে ডুপ্লিকেট কনসাইনমেন্ট হতে পারে। অর্ডারে ইতিমধ্যে tracking ID থাকলে আবার বুকিং করবেন না।',
      2 => 'দৈনন্দিন “কনফার্ম → বুক” রুটিনের বিস্তারিত /courier-auto-entry-তে। শিপিং লেবেল ও বারকোড এক ক্লিকে প্রিন্ট করে প্যাকিং লাইন দ্রুত চালান।',
    ),
  ),
  5 =>
  array (
    'heading' => 'ট্র্যাকিং ID, স্ট্যাটাস সিঙ্ক ও কাস্টমার মেসেজ',
    'paragraphs' =>
    array (
      0 => 'Steadfast বুকিং সম্পন্ন হলে কনসাইনমেন্ট/ট্র্যাকিং ID WooCommerce অর্ডারে সংরক্ষিত থাকা উচিত—সাপোর্ট টিম “আমার পার্সেল কোথায়?” প্রশ্নে সঙ্গে সঙ্গে উত্তর দিতে পারে। Webhook বা পোলিং দিয়ে In Transit, Out for Delivery, Delivered, Returned স্ট্যাটাস অর্ডারে ফিরে আসলে স্টক ও RTS অ্যালার্ট অটো ট্রিগার করা যায়।',
      1 => 'Out for Delivery মেসেজ বিশেষভাবে গুরুত্বপূর্ণ: কাস্টমার COD টাকা রেডি রাখে এবং অজানা নম্বর থেকে রাইডার কল ধরে—“customer unavailable” রিটার্ন কমে। WhatsApp প্রাথমিক, SMS fallback—টেমপ্লেট /woocommerce-notifications-এ কনফিগার করুন।',
      2 => 'Delivered-এর পর রিভিউ রিকোয়েস্ট ও রিপিট অফারও একই নোটিফিকেশন স্ট্যাকে যুক্ত করুন।',
    ),
  ),
  6 =>
  array (
    'heading' => 'বুকিংয়ের আগে ফ্রড চেক — Steadfast অটomation নিরাপদ রাখা',
    'paragraphs' =>
    array (
      0 => 'Steadfast API শক্তিশালী টুল, কিন্তু ভেরিফিকেশনের বিকল্প নয়। কুরিয়ার সাকসেস রেট দেখুন /bd-fraud-checker দিয়ে: সবুজ জোন (৮০–১০০%) দ্রুত কনফার্ম; হলুদ (৫০–৭৯%) কল বা OTP; লাল (৫০%-এর নিচে) অগ্রিম ডেলিভারি চার্জ বা hold—/fake-order-protection ও /customer-verification।',
      1 => '১০টি হাই-রিস্ক COD “Steadfast connected” বলে পাঠালে reverse fee, প্যাকেজিং ও অ্যাড CPA মিলিয়ে অনেক সফল ডেলিভারির লাভ মুছে যেতে পারে। মনtras: পরিষ্কার অর্ডারে অটো বুক; ঝুঁকিতে ধীর।',
      2 => 'COD গাণিতিক ও মাল্টি-লেয়ার প্রটেকশন /woocommerce-bangladesh হাবে। মাসিক লস /return-loss-calculator দিয়ে মাপুন।',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'Steadfast বুকিংয়ের আগে বহু-স্তর ফ্রড প্রটেকশন',
        'caption' => 'হিস্টোরি চেক + OTP/ব্ল্যাকলিস্ট + কনফার্ম — তারপর Steadfast অটো এন্ট্রি',
      ),
    ),
  ),
  7 =>
  array (
    'heading' => 'Steadfast API troubleshooting ও পরবর্তী পদক্ষেপ',
    'paragraphs' =>
    array (
      0 => 'Connection fail: API Key/Secret পুনরায় কপি করুন, merchant API enable আছে কিনা দেখুন, extra space সরান। Auth error password/key change-এর পর WooEasyLife settings আপডেট করুন। Booking fail on area: checkout-এ city/zone/area mapping ঠিক করুন বা কাস্টমার থেকে স্পষ্ট ঠিকানা নিন।',
      1 => 'COD mismatch: কার্ট টোটাল, shipping line ও প্রমised delivery charge মিলিয়ে একবার reconcile করে rebook করুন। Pathao/RedX প্যাটার্ন তুলনা: /pathao-courier-guide, /redx-courier-guide।',
      2 => 'চেকলিস্ট: Steadfast credentials ✓ → connection test green ✓ → warehouse mapped ✓ → টেস্ট পার্সেল OK ✓ → fraud layers ✓ → notifications ✓ → শুধু কনফার্মড অর্ডারে bulk ✓। ইংরেজি: /en/steadfast-integration।',
    ),
  ),
);
