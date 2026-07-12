<!DOCTYPE html>
<html lang="{{ ($seo['html_lang'] ?? null) ?: str_replace('_', '-', app()->getLocale()) }}" class="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ ($seo['title'] ?? null) ?: config('app.name', 'Laravel') }}</title>

    @isset($seo)
        @if (! empty($seo['description']))
            <meta name="description" content="{{ $seo['description'] }}">
        @endif
        @if (! empty($seo['robots']))
            <meta name="robots" content="{{ $seo['robots'] }}">
        @endif
        @if (! empty($seo['canonical']))
            <link rel="canonical" href="{{ $seo['canonical'] }}">
        @endif
        @foreach (($seo['hreflang'] ?? []) as $alt)
            @if (! empty($alt['hreflang']) && ! empty($alt['url']))
                <link rel="alternate" hreflang="{{ $alt['hreflang'] }}" href="{{ $alt['url'] }}">
            @endif
        @endforeach
        @if (! empty($seo['title']))
            <meta property="og:title" content="{{ $seo['title'] }}">
            <meta name="twitter:title" content="{{ $seo['title'] }}">
        @endif
        @if (! empty($seo['description']))
            <meta property="og:description" content="{{ $seo['description'] }}">
            <meta name="twitter:description" content="{{ $seo['description'] }}">
        @endif
        @if (! empty($seo['canonical']))
            <meta property="og:url" content="{{ $seo['canonical'] }}">
        @endif
        <meta property="og:type" content="{{ $seo['og_type'] ?? 'website' }}">
        <meta property="og:locale" content="{{ str_replace('-', '_', $seo['html_lang'] ?? 'bn_BD') }}">
        <meta property="og:site_name" content="{{ config('seo.site_name', 'WooEasyLife') }}">
        @if (! empty($seo['og_image']))
            <meta property="og:image" content="{{ $seo['og_image'] }}">
            <meta property="og:image:width" content="{{ $seo['og_image_width'] ?? 1200 }}">
            <meta property="og:image:height" content="{{ $seo['og_image_height'] ?? 630 }}">
            <meta name="twitter:image" content="{{ $seo['og_image'] }}">
            <link rel="preload" as="image" href="{{ $seo['og_image'] }}" fetchpriority="high">
        @endif
        <meta name="twitter:card" content="summary_large_image">
        @if (! empty($seo['json_ld']))
            <script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        @endif
    @endisset

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @routes
    <style>
        #app-loader,
        #app-navigation-loader {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0a0a;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }

        #app-loader.is-hidden,
        #app-navigation-loader.is-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .app-loader-spinner {
            position: relative;
            width: 5.5rem;
            height: 5.5rem;
            flex-shrink: 0;
        }

        .app-loader-spinner__logo {
            position: absolute;
            top: 1.1rem;
            left: 1.1rem;
            width: 3.3rem;
            height: 3.3rem;
            max-width: 3.3rem;
            max-height: 3.3rem;
            border-radius: 9999px;
            object-fit: cover;
            display: block;
        }

        .app-loader-spinner__ring {
            position: absolute;
            inset: 0;
            border-radius: 9999px;
            border: 3px solid transparent;
            box-sizing: border-box;
        }

        .app-loader-spinner__ring--one {
            border-top-color: #ffc107;
            border-right-color: #ffc107;
            animation: app-loader-spin 0.9s linear infinite;
        }

        .app-loader-spinner__ring--two {
            inset: 0.35rem;
            border-bottom-color: #ffd54f;
            border-left-color: #ffd54f;
            animation: app-loader-spin 1.2s linear infinite reverse;
        }

        @keyframes app-loader-spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Crawlable body copy until Vue hydrates (hidden after mount). */
        html.app-ready #seo-prerender {
            display: none !important;
        }

        #seo-prerender {
            max-width: 48rem;
            margin: 0 auto;
            padding: 1.5rem 1rem 2rem;
            color: #e2e8f0;
            background: #0a0a0a;
            font-family: system-ui, sans-serif;
        }

        #seo-prerender h1 {
            font-size: 1.5rem;
            line-height: 1.35;
            color: #fff;
            margin: 0 0 0.75rem;
        }

        #seo-prerender p {
            margin: 0 0 1rem;
            line-height: 1.6;
            color: #94a3b8;
        }

        #seo-prerender ul {
            margin: 0;
            padding-left: 1.15rem;
        }

        #seo-prerender a {
            color: #fbbf24;
        }
    </style>
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @isset($seo)
        <section id="seo-prerender">
            <h1>{{ $seo['prerender_h1'] ?? $seo['title'] ?? 'WooEasyLife' }}</h1>
            @if (! empty($seo['prerender_lead'] ?? $seo['description'] ?? null))
                <p>{{ $seo['prerender_lead'] ?? $seo['description'] }}</p>
            @endif
            <ul>
                <li><a href="/bd-fraud-checker">BD Fraud Checker / ফ্রড চেকার</a></li>
                <li><a href="/fake-order-protection">ফেক অর্ডার প্রোটেকশন</a></li>
                <li><a href="/courier-auto-entry">কুরিয়ার অটো এন্ট্রি</a></li>
                <li><a href="/fraudbd-alternative">FraudBD Alternative</a></li>
                <li><a href="/pricing">প্রাইসিং</a></li>
            </ul>
        </section>
        <noscript>
            <section style="max-width:48rem;margin:0 auto;padding:1.5rem 1rem;color:#e2e8f0;background:#0a0a0a;font-family:system-ui,sans-serif">
                <h1>{{ $seo['prerender_h1'] ?? $seo['title'] ?? 'WooEasyLife' }}</h1>
                <p>{{ $seo['prerender_lead'] ?? $seo['description'] ?? '' }}</p>
                <p><a href="/bd-fraud-checker" style="color:#fbbf24">ফ্রি ফ্রড চেক</a> · <a href="/pricing" style="color:#fbbf24">প্রাইসিং</a></p>
            </section>
        </noscript>
    @endisset

    <div id="app-loader" aria-live="polite" aria-busy="true">
        <div class="app-loader-spinner" role="status" aria-label="লোড হচ্ছে">
            <div class="app-loader-spinner__ring app-loader-spinner__ring--one"></div>
            <div class="app-loader-spinner__ring app-loader-spinner__ring--two"></div>
            <img src="/app-logo" alt="WooEasyLife" class="app-loader-spinner__logo" width="53" height="53">
        </div>
    </div>

    @inertia
</body>

</html>
