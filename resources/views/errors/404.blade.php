<!DOCTYPE html>
<html lang="bn-BD">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] ?? 'পেজ পাওয়া যায়নি — WooEasyLife' }}</title>
    <meta name="robots" content="noindex,follow">
    @if (! empty($seo['description']))
        <meta name="description" content="{{ $seo['description'] }}">
    @endif
    <link rel="canonical" href="{{ url('/') }}">
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #0a0a0a; color: #e2e8f0; }
        .wrap { max-width: 40rem; margin: 0 auto; padding: 4rem 1.25rem; }
        h1 { font-size: 1.75rem; color: #fff; margin: 0 0 0.75rem; }
        p { line-height: 1.6; color: #94a3b8; }
        ul { padding-left: 1.1rem; }
        a { color: #fbbf24; text-decoration: none; }
        a:hover { text-decoration: underline; }
        li { margin: 0.5rem 0; }
    </style>
</head>
<body>
    <main class="wrap">
        <h1>পেজ পাওয়া যায়নি</h1>
        <p>এই লিংকটি ভুল বা পেজটি সরানো হয়েছে। নিচ থেকে চালিয়ে যান — সাবস্ক্রিপশন ও ফ্রড চেক আগের মতোই কাজ করছে।</p>
        <ul>
            <li><a href="/">হোম</a></li>
            <li><a href="/bd-fraud-checker">BD Fraud Checker / ফ্রড চেকার</a></li>
            <li><a href="/return-loss-calculator">রিটার্ন লস ক্যালকুলেটর</a></li>
            <li><a href="/courier-charge-calculator">কুরিয়ার চার্জ ক্যালকুলেটর</a></li>
            <li><a href="/ads-roas-calculator">Ads ROAS ক্যালকুলেটর</a></li>
            <li><a href="/fake-order-protection">ফেক অর্ডার প্রোটেকশন</a></li>
            <li><a href="/courier-auto-entry">কুরিয়ার অটো এন্ট্রি</a></li>
            <li><a href="/pricing">প্রাইসিং ও সাবস্ক্রিপশন</a></li>
        </ul>
    </main>
</body>
</html>
