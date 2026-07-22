<?php

return array (
  0 => 
  array (
    'heading' => 'WooCommerce Notifications — দ্রুত উত্তর',
    'paragraphs' => 
    array (
      0 => 'SMS, WhatsApp ও email automation: incomplete checkout recovery, order confirm, courier tracking (In Transit / Out for Delivery / Delivered), post-delivery review—Bangladesh COD shop-এ conversion ও RTS control একসাথে।',
      1 => 'WooEasyLife wallet + template engine-এ OTP, recovery, tracking এক জায়গায়। courier book: /courier-auto-entry। fraud gate: /bd-fraud-checker। ইংরেজি: /en/woocommerce-notifications। হাব: /woocommerce-bangladesh।',
    ),
  ),
  1 => 
  array (
    'heading' => 'Incomplete order recovery — ৬০–৭০% leak ফেরান',
    'paragraphs' => 
    array (
      0 => 'Bangladesh mobile checkout dropout প্রায় ৬০–৭০%। standard WooCommerce Place Order পর্যন্ত save করে না—phone/name টাইপ করলেই backend incomplete row তৈরি, ৫–১৫ মিনিটে WhatsApp/SMS follow-up।',
      1 => 'WhatsApp open rate ~৯৫% বনাম email ~২০%—recovery-এর primary channel WhatsApp, OTP/tracking fallback SMS। message-এ name, cart item, one-tap checkout link, support number দিন। ২-step sequence max—spam feeling trust নষ্ট করে।',
      2 => 'উদাহরণ: ১,০০০ incomplete/মাস, ১৫% recover, AOV ১,০০০ = ১,৫০,০০০ টাকা extra revenue, নতুন ad ছাড়াই। /facebook-ads-for-woocommerce incomplete audience sync-এর সাথে জোড়া দিন।',
    ),
  ),
  2 => 
  array (
    'heading' => 'Order confirm ও processing messages',
    'paragraphs' => 
    array (
      0 => 'Verified order-এর পর instant confirm: order ID, product summary, COD total (delivery সহ), expected ship day। expectation set—“কখন আসবে?” inbound call কমে।',
      1 => 'dynamic tags: {customer_name}, {order_id}, {product}, {cod_amount}, {area}। Bangla short polite tone; ALL CAPS avoid করুন।',
      2 => 'yellow zone call pending থাকলে optional: “আমরা শীঘ্রই confirm call করব”—/customer-verification workflow-এর সাথে align।',
    ),
  ),
  3 => 
  array (
    'heading' => 'Courier tracking SMS/WhatsApp lifecycle',
    'paragraphs' => 
    array (
      0 => 'courier API tracking ID save হলে auto dispatch message + live link (Steadfast/Pathao/RedX)। In Transit: “পার্সেল রوانা”। Out for Delivery: সকালে COD ready + rider call ধরুন—RTS কমানোর সবচেয়ে ROI বেশি message। Delivered: thank you + পরে review link।',
      1 => 'setup: /steadfast-integration, /pathao-courier-guide, /redx-courier-guide। booking trigger /courier-auto-entry।',
      2 => 'optional branded track page yourdomain.com/track—upsell banner কিছু shop-এ ৫–১০% extra order দেখায়।',
    ),
    'figures' => 
    array (
      0 => 
      array (
        'src' => '/images/seo/cluster/courier-auto-entry.jpg',
        'alt' => 'Tracking notification after courier auto entry',
        'caption' => 'Confirm → courier book → tracking ID → auto SMS/WhatsApp',
      ),
    ),
  ),
  4 => 
  array (
    'heading' => 'WhatsApp + SMS combo engine',
    'paragraphs' => 
    array (
      0 => 'primary WhatsApp rich template (product thumb optional), undelivered হলে minutes-এ SMS fallback। central wallet balance alert admin—OTP burst-এ empty wallet = checkout broken।',
      1 => 'dedupe: same event-এ SMS+WA identical ৩ বার নয়। DND window রাত ১১–৮ Bangladesh courtesy। short track link + UTM recover campaign measure।',
      2 => 'OTP template আলাদা shorter—/customer-verification conditional rules volume drive করে।',
    ),
  ),
  5 => 
  array (
    'heading' => 'Post-delivery review automation',
    'paragraphs' => 
    array (
      0 => 'Delivered +24–48h WhatsApp: “পেয়েছেন? ১–৫ স্টার feedback”। ৪–৫ star → public Google/Facebook review link। ১–৩ star → private support form—public negative avoid।',
      1 => 'next purchase ছোট coupon—LTV up, ad dependency down। review social proof /facebook-ads-for-woocommerce creative-এ feed হয়।',
      2 => 'delivery same day review চাইবেন না—customer unpacking-এ busy।',
    ),
  ),
  6 => 
  array (
    'heading' => 'Notification × fraud × returns',
    'paragraphs' => 
    array (
      0 => 'notification /bd-fraud-checker replace করে না—তবে out-for-delivery legitimate RTS (cash not ready, missed call) কমায়। fake order recovery answer দেয় না—genuine intent dropout-এ recovery ROI real।',
      1 => '/cod-return-reduction playbook layer 3 = এই page-এর tracking templates। out-for-delivery enable-এর ২ সপ্তাহ before/after RTS measure করুন।',
      2 => '/return-loss-calculator-এ notification save separately attribute করুন সম্ভব হলে।',
    ),
  ),
  7 => 
  array (
    'heading' => 'Template examples (Bangla)',
    'paragraphs' => 
    array (
      0 => 'Incomplete: “{name}, আপনার {product} কার্টে আছে—১ ক্লিকে অর্ডার শেষ করুন: {link}। সাহায্য: {phone}”',
      1 => 'Out for delivery: “{name}, আজ {order_id} ডেলিভারি। COD {cod} টাকা ready রাখুন। Rider call ধরুন। Track: {link}”',
      2 => 'Delivered review: “{name}, পার্সেল পেয়েছেন? মতামত দিন: {review_link}—পরের অর্ডারে {coupon}”',
    ),
  ),
  8 => 
  array (
    'heading' => 'Setup checklist',
    'paragraphs' => 
    array (
      0 => 'wallet top-up ✓ incomplete capture ✓ recovery ৫–১৫ min ✓ confirm template ✓ dispatch on tracking ID ✓ out-for-delivery morning ✓ delivered review delayed ✓ DND ✓ EN /en/woocommerce-notifications',
      1 => 'Related: /courier-auto-entry, /customer-verification, /fake-order-protection, /woocommerce-bangladesh, /pricing trial।',
      2 => 'peak season-এর আগে এক real order end-to-end test—notification failure silent profit leak।',
    ),
  ),
  9 => 
  array (
    'heading' => 'কেন নোটিফিকেশন COD লাভ বাড়ায়',
    'paragraphs' => 
    array (
      0 => 'বাংলাদেশে চেকআউট ইনকমপ্লিট রেট অনেক দোকানে ৬০–৭০% পর্যন্ত যায়। নম্বর সেভ হওয়ার পর ৫–১৫ মিনিটের মধ্যে WhatsApp/SMS রিকভারি পাঠালে নতুন অ্যাড খরচ ছাড়াই অর্ডার ফিরে আসে।',
      1 => 'শিপমেন্টের পর “পার্সেল কোথায়?” কল সাপোর্ট ঘণ্টা খায়। In Transit ও বিশেষ করে Out for Delivery মেসেজ কাস্টমারকে COD টাকা রেডি ও রাইডার কল এক্সপেক্ট করতে শেখায়—কাস্টমার নট এভেলেবল রিটার্ন কমে।',
      2 => 'সেটআপ ক্রম: টেমপ্লেট চালু → কুরিয়ার ট্র্যাকিং সিঙ্ক (/courier-auto-entry) → এক টেস্ট অর্ডার → /cod-return-reduction KPI। হাব: /woocommerce-bangladesh। ইংরেজি: /en/woocommerce-notifications। ট্রায়াল: /pricing।',
    ),
  ),
);
