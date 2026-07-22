<?php

return array (
  0 =>
  array (
    'heading' => 'কাস্টমার ভেরিফিকেশন — দ্রুত উত্তর',
    'paragraphs' =>
    array (
      0 => 'Bangladesh COD-এ random phone দিয়ে fake order common। OTP, courier success-rate zone (সবুজ/হলুদ/লাল), conditional OTP, call confirm ও advance delivery charge—একসাথে junk shipment কমায়।',
      1 => 'WooEasyLife stack: /bd-fraud-checker history → checkout OTP rules → /fake-order-protection → confirm → /courier-auto-entry। ইংরেজি: /en/customer-verification। হাব: /woocommerce-bangladesh।',
    ),
  ),
  1 =>
  array (
    'heading' => 'COD-এ ভেরিফিকেশন কেন optional নয়',
    'paragraphs' =>
    array (
      0 => 'Cash on Delivery-এ buyer অনলাইনে টাকা commit করে না—তাই last-minute refuse সহজ। Facebook ad থেকে prank order, competitor spam, wrong number typo—সব merchant panel-এ “confirmed” দেখায় কিন্তু rider-এ গিয়ে fail। প্রতি failed attempt-এ forward + return courier charge, packaging, staff call time।',
      1 => 'শুধু manual call confirm ২০২৬ scale-এ enough নয়—দিনে ৮০+ order-এ ৩ জন agent full day call করলেও duplicate miss হয়। Automated history score + conditional OTP + rules engine repeatable guardrail দেয়।',
      2 => 'math: ৫০ fake × ~১৩০ টাকা reverse ≈ ৬,৫০০ টাকা/মাস; OTP SMS cost ~৩০০–৪০০ টাকা—net save স্পষ্ট। /return-loss-calculator-এ আপনার shop number plug করুন।',
    ),
  ),
  2 =>
  array (
    'heading' => 'OTP verification — checkout ও post-order',
    'paragraphs' =>
    array (
      0 => 'Checkout OTP: Place Order-এর আগে SMS/WhatsApp code verify—random number দিয়ে order complete impossible। WooEasyLife conditional OTP enable করুন: সব order-এ নয়, শুধু high-risk signal-এ (first-time buyer, high AOV, suspicious IP, yellow/red courier history)।',
      1 => 'Post-order OTP: checkout bypass হলে (guest fast checkout) confirm-এর আগে agent trigger OTP link—customer code দিলে তবেই processing। template /woocommerce-notifications wallet-এ।',
      2 => 'OTP rate limit ও resend cooldown abuse prevent করে। DND window (রাত ১১–৮) respect—Bangladesh customer experience।',
    ),
  ),
  3 =>
  array (
    'heading' => 'সবুজ, হলুদ, লাল zone — courier success rate',
    'paragraphs' =>
    array (
      0 => 'সবুজ zone (৮০–১০০% success): regular online buyer, parcel receive করে। Fast confirm, COD ship OK—still duplicate check /fake-order-protection।',
      1 => 'হলুদ zone (৫০–৭৯%): occasional refuse। Call confirm বা lightweight OTP recommended before pack। agent script: product, COD amount, delivery day expectation।',
      2 => 'লাল zone (৫০%-এর নিচ): high risk—multiple past returns/refusals। advance delivery charge (partial prepay) popup, hold status, or block COD—/fake-order-protection rules। ship without filter = /cod-return-reduction case study material।',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'সবুজ হলুদ লাল zone ও verification layers',
        'caption' => 'Courier history zone → OTP/call/advance → confirm → ship',
      ),
    ),
  ),
  4 =>
  array (
    'heading' => 'Conditional OTP — smart rule design',
    'paragraphs' =>
    array (
      0 => 'Every order OTP conversion hurt—green repeat customer friction unnecessary। Rules example: OTP if courier history unknown OR yellow/red OR order value > ৩,০০০ টাকা OR same IP ৩+ orders/১ hour OR blacklist near-match।',
      1 => 'Green + low AOV + known phone → skip OTP, fast path /courier-auto-entry। log decision audit trail—dispute-এ “system said green” proof।',
      2 => 'A/B: OTP on all vs conditional—usually conditional wins on conversion with same fraud drop। tune monthly from RTS dashboard।',
    ),
  ),
  5 =>
  array (
    'heading' => 'Call confirm workflow — agent SOP',
    'paragraphs' =>
    array (
      0 => 'Yellow zone বা high-ticket: agent call within ৩০ min peak hours। script: নাম confirm, product repeat, full address with landmark, COD total including delivery, expected delivery window। “না নিলে return charge কার?”—soft commitment।',
      1 => 'No answer: WhatsApp voice note template + ২ follow-up window; still no response → cancel/hold, don’t book courier। /woocommerce-notifications missed-call recovery optional।',
      2 => 'Mobile app approve/hold from road—/woocommerce-bangladesh mobile ops chapter link mentally; same fraud flags on card।',
    ),
  ),
  6 =>
  array (
    'heading' => 'অগ্রিম ডেলিভারি চার্জ (partial advance)',
    'paragraphs' =>
    array (
      0 => 'লাল zone বা repeat offender-এ full COD ship economic suicide। partial advance (delivery charge only, e.g. ১২০–১৫০ টাকা bKash/Nagad) filter serious buyer—fake user dropout ~৭০%+।',
      1 => 'WooEasyLife popup checkout বা post-order payment link: “আপনার courier history অনুযায়ী delivery charge agroim প্রয়োজন”—transparent copy reduces anger vs hidden surprise।',
      2 => 'paid advance → auto green path confirm + Steadfast/Pathao/RedX book—/steadfast-integration, /pathao-courier-guide, /redx-courier-guide। unpaid timeout → auto cancel release stock।',
    ),
  ),
  7 =>
  array (
    'heading' => 'Verification + courier automation একসাথে',
    'paragraphs' =>
    array (
      0 => 'Verification without courier API still saves return fee; courier API without verification ships junk faster। ideal: /bd-fraud-checker at intake → rules at checkout → confirm gate → /courier-auto-entry only on “verified” status tag।',
      1 => 'duplicate phone ১৫ min: second order hold until first delivered or verified। IP burst rules /fake-order-protection। blacklist shared DB where available।',
      2 => 'weekly measure করুন: verification pass rate, zone অনুযায়ী RTS before/after, agent hours saved। EN: /en/customer-verification। trial: /pricing।',
    ),
  ),
  8 =>
  array (
    'heading' => 'Implementation checklist',
    'paragraphs' =>
    array (
      0 => 'চেকলিস্ট: /bd-fraud-checker checkout/admin-এ ✓ conditional OTP rules ✓ staff-এর জন্য green/yellow/red UI ✓ call SOP doc ✓ red zone advance fee ✓ শুধু verified-এ auto-book ✓ /woocommerce-notifications OTP templates ✓ মাসিক /return-loss-calculator review ✓',
      1 => 'সম্পর্কিত: /fake-order-protection, /cod-return-reduction, /courier-auto-entry, /woocommerce-bangladesh।',
      2 => 'verification ছাড়া fake COD-এ ad budget বাড়াবেন না—/facebook-ads-for-woocommerce-এ ROAS অংশ পড়ুন।',
    ),
  ),
);
