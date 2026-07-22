<?php

return array (
  0 => 
  array (
    'heading' => 'Facebook Ads + WooCommerce — দ্রুত উত্তর',
    'paragraphs' => 
    array (
      0 => 'Meta Pixel একা browser event হারায় (iOS, ad block)। Pixel + Conversions API (CAPI), shared event_id dedupe, purchased/incomplete audience sync—ROAS real courier-delivered COD-এ map করুন; fake purchase-এ budget বাড়াবেন না।',
      1 => 'guardrails: /bd-fraud-checker + /customer-verification before scale। ROAS math: /ads-roas-calculator। return bleed: /return-loss-calculator। ইংরেজি: /en/facebook-ads-for-woocommerce। হাব: /woocommerce-bangladesh।',
    ),
  ),
  1 => 
  array (
    'heading' => 'Browser Pixel একা কেন যথেষ্ট নয়',
    'paragraphs' => 
    array (
      0 => 'thank-you page Purchase fire হলেও customer parcel refuse করলে business loss—Pixel still “converted” দেখায়। iOS ATT + ad blocker ২০–৪০% event drop common; Bangladesh Android-heavy হলেও gap থাকে।',
      1 => 'CAPI server-side Purchase, InitiateCheckout, AddToCart—WooEasyLife বা server plugin order confirmed/delivered status-এ fire করে, শুধু browser নয়।',
      2 => 'event_id browser+server same রাখলে Meta double count prevent হয়।',
    ),
    'figures' => 
    array (
      0 => 
      array (
        'src' => '/images/seo/cluster/pixel-vs-capi.jpg',
        'alt' => 'Meta Pixel vs Conversions API event flow',
        'caption' => 'Browser Pixel + CAPI dedupe—COD delivered truth server-side',
      ),
    ),
  ),
  2 => 
  array (
    'heading' => 'ROAS পরিষ্কার — COD Bangladesh math',
    'paragraphs' => 
    array (
      0 => 'ROAS = revenue / ad spend, কিন্তু COD revenue = delivered COD only। ১.৫ ROAS orders-এ ২৫% RTS থাকলে COGS + courier + return penalty-এর পর net negative হতে পারে।',
      1 => '/ads-roas-calculator-এ AOV, CPA, delivery, RTS %, COGS plug করে budget increase-এর আগে true break-even ROAS দেখুন।',
      2 => '/return-loss-calculator parallel—RTS ৫% drop +২০% ad spend-ের চেয়ে বেশি মূল্যবান হতে পারে।',
    ),
  ),
  3 => 
  array (
    'heading' => 'ফেক COD-এ ad budget বাড়ানো নয়',
    'paragraphs' => 
    array (
      0 => 'weak verification-এ fake/prank InitiateCheckout বা Purchase trigger—Meta wrong audience শেখে। budget scale → more junk → courier loss spiral।',
      1 => 'scale-এর minimum: /fake-order-protection, /customer-verification conditional OTP, /bd-fraud-checker green fast red block। CAPI Purchase “confirmed verified” বা “delivered”-এ fire, raw checkout নয়।',
      2 => 'weekly: ad orders RTS % vs organic। ad RTS >> organic = pixel misleading, আগে verification fix—not creative।',
    ),
    'figures' => 
    array (
      0 => 
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'Ad scale fraud protection layers',
        'caption' => 'Verify before CAPI Purchase → Meta learns real buyers',
      ),
    ),
  ),
  4 => 
  array (
    'heading' => 'Pixel + CAPI implementation steps',
    'paragraphs' => 
    array (
      0 => '১) Meta Business Manager Pixel ID। ২) CAPI access token server-side। ৩) WooEasyLife Meta integration Purchase/InitiateCheckout/AddToCart mapping enable। ৪) event_id browser+server generate। ৫) Test Events tool dedupe verify।',
      1 => 'Purchase delay option: fraud pass-এর পর processing/confirmed-এ fire—thank-you alone-এর চেয়ে economic truth কাছাকাছি। Delivered event advanced LTV campaign-এ।',
      2 => 'Incomplete audience: /woocommerce-notifications recovery + Meta custom audience warm dropout retarget।',
    ),
  ),
  5 => 
  array (
    'heading' => 'Audience: purchased, incomplete, VIP',
    'paragraphs' => 
    array (
      0 => 'delivered purchased ১% lookalike Bangladesh geo—CRM tag থাকলে red zone repeat RTS phone exclude। incomplete ৭-day window soft retarget, hard spam নয়।',
      1 => 'VIP repeat green zone buyers—lower CPA creative, new SKU upsell। recent RTS cancel list prospecting exclude।',
      2 => 'audience sync daily; stale audience waste spend।',
    ),
  ),
  6 => 
  array (
    'heading' => 'Ops loop: ads ↔ courier ↔ notifications',
    'paragraphs' => 
    array (
      0 => 'ad click → fast mobile checkout → fraud check → confirm → /courier-auto-entry → /woocommerce-notifications tracking → delivered CAPI Purchase reconcile।',
      1 => 'যেকোনো link break = ROAS dashboard fiction। courier: /steadfast-integration, /pathao-courier-guide, /redx-courier-guide।',
      2 => 'spend ২x scale-এর আগে /cod-return-reduction playbook।',
    ),
  ),
  7 => 
  array (
    'heading' => 'Common Meta ads mistakes BD WooCommerce',
    'paragraphs' => 
    array (
      0 => '৩০% RTS থাকলেও Purchase optimize—verified confirm বা delivered server event optimize করা উচিত। broad age/gender, Dhaka vs district mix cap না করা।',
      1 => 'incomplete retarget না—same user full CAC again। /ads-roas-calculator delivered-adjusted column ignore।',
      2 => 'fraud gate off রেখে creative scale—CPA ৪৮ hours good, পরে RTS bill।',
    ),
  ),
  8 => 
  array (
    'heading' => 'Checklist ও next steps',
    'paragraphs' => 
    array (
      0 => 'Pixel + CAPI ✓ event_id dedupe ✓ Purchase verified/delivered ✓ fraud layers ✓ /ads-roas-calculator baseline ✓ incomplete audience ✓ RTS weekly ✓ EN /en/facebook-ads-for-woocommerce ✓ /pricing',
      1 => 'Related: /return-loss-calculator, /fake-order-protection, /customer-verification, /woocommerce-notifications, /woocommerce-bangladesh।',
      2 => 'Clean data first, then budget—Bangladesh COD sustainable scale formula।',
    ),
  ),
  9 => 
  array (
    'heading' => 'ফেক COD থাকলে অ্যাড বাড়ানোর আগে যা করবেন',
    'paragraphs' => 
    array (
      0 => 'Meta Pixel শুধু ব্রাউজার ইভেন্ট দেখে; iOS ও অ্যাডব্লকারে Purchase হারায়। Conversions API সার্ভার থেকে সিগন্যাল পাঠিয়ে ইভেন্ট কোয়ালিটি বাড়ায়—তবে ফেক/রিটার্ন অর্ডারকে Purchase ধরলে ROAS মিথ্যা সুন্দর দেখায়।',
      1 => 'স্কেল করার আগে: /bd-fraud-checker ও /fake-order-protection চালু, /return-loss-calculator দিয়ে RTS ৳ বেসলাইন, /ads-roas-calculator দিয়ে ডেলিভারি-অ্যাডজাস্টেড ROAS। ইনকমপ্লিট রিকভারি /woocommerce-notifications দিয়ে অ্যাড ছাড়াই রেভিনিউ বাড়ান।',
      2 => 'ভেরিফায়েড/ডেলিভার্ড সিগন্যাল স্থিতিশীল হলে বাজেট বাড়ান। পূর্ণ কনটেক্সট: /woocommerce-bangladesh। ইংরেজি: /en/facebook-ads-for-woocommerce। শুরু: /pricing।',
    ),
  ),
);
