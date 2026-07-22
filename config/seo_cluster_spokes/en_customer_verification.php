<?php

return array (
  0 =>
  array (
    'heading' => 'Customer verification for Bangladesh COD orders',
    'paragraphs' =>
    array (
      0 => 'Cash on delivery in Bangladesh lets anyone type a random 11-digit number and place an order—no payment, no commitment. Competitors, pranksters, and mistaken buyers create fake COD orders that cost packaging, forward delivery, and return penalties when the real owner refuses the parcel.',
      1 => 'Customer verification combines courier success-rate zones (green, yellow, red), checkout OTP, conditional rules, call confirmation, and advance delivery fees so only serious buyers reach your packing bench. This guide shows how to layer those checks in WooEasyLife before you confirm and ship.',
      2 => 'Bangla version: /customer-verification. Free number lookup: /en/bd-fraud-checker. Full COD system: /en/woocommerce-bangladesh. Checkout hardening: /en/fake-order-protection.',
    ),
    'figures' =>
    array (
      0 =>
      array (
        'src' => '/images/seo/cluster/fraud-layers.jpg',
        'alt' => 'Multi-layer customer verification for COD orders',
        'caption' => 'Courier history → OTP/blacklist → call confirm → ship only verified orders',
      ),
    ),
  ),
  1 =>
  array (
    'heading' => 'Why verification matters more than manual call confirm',
    'paragraphs' =>
    array (
      0 => 'Calling every COD order sounds safe until volume hits 60–100 per day. Staff miss calls, forget notes, and still ship numbers with terrible courier history because the call “felt okay.” Manual confirm alone cannot scale and does not stop prank orders placed with someone else’s phone number.',
      1 => 'Automated verification uses data: courier delivery databases show whether a mobile actually receives parcels, OTP proves the buyer controls the number, and conditional rules apply friction only where risk is high. Result: fewer fake orders without slowing every loyal repeat customer.',
      2 => 'Measure what fake COD already costs with /en/return-loss-calculator. Then compare against SMS/OTP spend—most shops save thousands of taka per month. Broader RTS strategy: /en/cod-return-reduction.',
    ),
  ),
  2 =>
  array (
    'heading' => 'Courier success zones: green, yellow, and red',
    'paragraphs' =>
    array (
      0 => 'WooEasyLife checks courier delivery history when a customer enters a mobile number. Green zone (roughly 80–100% success rate): the number regularly receives COD parcels. These orders can confirm and ship quickly—still spot-check very high AOV or suspicious notes.',
      1 => 'Yellow zone (50–79%): mixed history. Require call confirmation or OTP before you mark confirmed. Ask product, COD amount, and address aloud; note who called and when so the next shift does not double-dial.',
      2 => 'Red zone (under 50%): high return risk. Prompt advance delivery fee, hold the order, or block checkout entirely. Free lookup anytime on /en/bd-fraud-checker before you pack.',
    ),
  ),
  3 =>
  array (
    'heading' => 'OTP verification at checkout',
    'paragraphs' =>
    array (
      0 => 'OTP sends a one-time code via SMS or WhatsApp before Place Order completes. Only someone with the phone can finish checkout—prank orders using random or stolen numbers drop sharply.',
      1 => 'Flow: customer enters mobile → system sends 4–6 digit code → customer enters code → order saves. Pair with /en/fake-order-protection for duplicate-number blocks, blacklists, and rate limits on repeat offenders.',
      2 => 'Worked example: 50 fake orders × Tk 130 return penalty ≈ Tk 6,500 lost monthly. OTP on high-risk segments might cost Tk 300–400 in SMS—net save often exceeds Tk 6,000. Notification templates and delivery: /en/woocommerce-notifications.',
    ),
  ),
  4 =>
  array (
    'heading' => 'Conditional OTP: verify smart, not every order',
    'paragraphs' =>
    array (
      0 => 'OTP on every order adds SMS cost and checkout friction for trusted repeat buyers. Conditional OTP applies verification only when rules fire—keeping conversion healthy while blocking fakes.',
      1 => 'Common rules: require OTP when courier success is under 50% (red zone); when order value exceeds Tk 3,000–5,000; when the buyer is first-time on your store; or when the same IP/device places multiple COD attempts in an hour.',
      2 => 'Trusted repeat customers with green history skip OTP and checkout faster. Adjust thresholds as you learn your audience. Deep checkout config: /en/fake-order-protection.',
    ),
  ),
  5 =>
  array (
    'heading' => 'Call confirmation for yellow-zone orders',
    'paragraphs' =>
    array (
      0 => 'Yellow-zone buyers need human contact before packing. Call from the order card—WooEasyLife and /en/woocommerce-mobile-app support one-tap dial and WhatsApp templates with order ID, product, COD amount, and area.',
      1 => 'Script basics: confirm they placed the order, read back address and COD total, and ask when they can receive. If they hesitate or deny ordering, cancel before courier booking. Never auto-ship yellow numbers without a logged confirm.',
      2 => 'Team discipline: one owner per order, timestamp notes, no duplicate calls that annoy customers. After verbal confirm, proceed to /en/courier-auto-entry only if history and notes still look clean.',
    ),
  ),
  6 =>
  array (
    'heading' => 'Advance delivery fee for red-zone customers',
    'paragraphs' =>
    array (
      0 => 'Red-zone numbers have a pattern of refused or failed deliveries. Shipping COD anyway sends packaging and forward fees into a likely return. Advance delivery fee (partial or full shipping paid upfront) filters fake buyers who never intended to pay.',
      1 => 'Show a clear popup or checkout notice: “This number has low delivery success—pay Tk X delivery charge now to confirm.” Serious buyers pay; prank orders abandon. Combine with OTP for maximum filter on repeat offenders.',
      2 => 'Track how many red orders convert versus cancel—tune fee amount so you block fakes without killing legitimate edge cases. Full return math: /en/cod-return-reduction and /en/return-loss-calculator.',
    ),
  ),
  7 =>
  array (
    'heading' => 'Verification workflow before courier booking',
    'paragraphs' =>
    array (
      0 => 'Recommended sequence: order placed → courier history check (/en/bd-fraud-checker) → apply zone rules (OTP, call, advance fee) → mark confirmed → auto courier entry. Skipping verification because courier API is connected multiplies return loss.',
      1 => 'On mobile, review zone flags in /en/woocommerce-mobile-app before you tap confirm. Push alerts for new COD and incomplete checkouts via /en/woocommerce-notifications so verification starts within minutes, not hours.',
      2 => 'After verification, Steadfast/Pathao/RedX booking from /en/courier-auto-entry is safe automation—not a substitute for the checks above. Hub overview: /en/woocommerce-bangladesh.',
    ),
  ),
  8 =>
  array (
    'heading' => 'Next steps: turn verification on today',
    'paragraphs' =>
    array (
      0 => 'Checklist: enable /en/bd-fraud-checker lookups → configure conditional OTP and blacklists on /en/fake-order-protection → set advance fee rules for red zone → train staff on yellow call confirm → connect courier auto entry only after confirm.',
      1 => 'Measure baseline return loss on /en/return-loss-calculator, enable verification, remeasure after 30 days. Start a trial from /pricing. Bangla guide: /customer-verification.',
    ),
  ),
);
