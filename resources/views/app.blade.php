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
        @php
            $htmlLang = (string) ($seo['html_lang'] ?? 'bn-BD');
            $ogLocale = str_starts_with(strtolower($htmlLang), 'en')
                ? 'en_US'
                : (str_starts_with(strtolower($htmlLang), 'bn') ? 'bn_BD' : str_replace('-', '_', $htmlLang));
            $ogLocaleAlt = $ogLocale === 'en_US' ? 'bn_BD' : 'en_US';
        @endphp
        <meta property="og:locale" content="{{ $ogLocale }}">
        <meta property="og:locale:alternate" content="{{ $ogLocaleAlt }}">
        <meta property="og:site_name" content="{{ config('seo.site_name', 'WooEasyLife') }}">
        @if (! empty($seo['facebook_app_id']))
            <meta property="fb:app_id" content="{{ $seo['facebook_app_id'] }}">
        @endif
        @if (! empty($seo['og_image']))
            <meta property="og:image" content="{{ $seo['og_image'] }}">
            <meta property="og:image:width" content="{{ $seo['og_image_width'] ?? 1200 }}">
            <meta property="og:image:height" content="{{ $seo['og_image_height'] ?? 630 }}">
            <meta property="og:image:type" content="{{ $seo['og_image_type'] ?? 'image/jpeg' }}">
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

        #seo-prerender h2 {
            font-size: 1.125rem;
            line-height: 1.4;
            color: #f8fafc;
            margin: 1.25rem 0 0.5rem;
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

        #seo-prerender figure {
            margin: 1rem 0 1.25rem;
        }

        #seo-prerender figure img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        #seo-prerender figcaption {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #94a3b8;
        }

        #seo-prerender .ssr-fraud-checker {
            margin: 1.25rem 0 1.5rem;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 0.75rem;
            background: #141414;
        }

        #seo-prerender .ssr-fraud-checker h2 {
            margin: 0 0 0.35rem;
            font-size: 1rem;
            color: #fff;
        }

        #seo-prerender .ssr-fraud-checker .ssr-fraud-hint {
            margin: 0 0 0.85rem;
            font-size: 0.875rem;
            color: #94a3b8;
        }

        #seo-prerender .ssr-fraud-checker form {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-end;
        }

        #seo-prerender .ssr-fraud-checker label {
            flex: 1 1 12rem;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #e2e8f0;
        }

        #seo-prerender .ssr-fraud-checker input[type="tel"] {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 0.65rem;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            padding: 0.8rem 0.9rem;
            font-size: 1rem;
        }

        #seo-prerender .ssr-fraud-checker button {
            border: 0;
            border-radius: 0.65rem;
            background: linear-gradient(90deg, #f59e0b, #eab308);
            color: #000;
            font-weight: 700;
            font-size: 0.875rem;
            padding: 0.85rem 1.15rem;
            cursor: pointer;
        }

        #seo-prerender .ssr-calc-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));
            gap: 0.75rem;
            align-items: end;
        }

        #seo-prerender .ssr-calc-form input[type="number"] {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 0.65rem;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            padding: 0.8rem 0.9rem;
            font-size: 1rem;
        }
    </style>
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead

    @if (! empty($metaPixelId))
        <!-- Meta Pixel Code -->
        <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', @json($metaPixelId));
            fbq('track', 'PageView');
        </script>
        <!-- End Meta Pixel Code -->
    @endif
</head>

<body class="font-sans antialiased">
    @if (! empty($metaPixelId))
        <noscript><img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id={{ urlencode($metaPixelId) }}&ev=PageView&noscript=1"
            alt=""
        /></noscript>
    @endif

    @isset($seo)
        @php
            $isEnPrerender = str_starts_with((string) ($seo['html_lang'] ?? 'bn-BD'), 'en');
        @endphp
        <section id="seo-prerender">
            <h1>{{ $seo['prerender_h1'] ?? $seo['title'] ?? 'WooEasyLife' }}</h1>
            @if (! empty($seo['prerender_lead'] ?? $seo['description'] ?? null))
                <p>{{ $seo['prerender_lead'] ?? $seo['description'] }}</p>
            @endif
            @if (! empty($seo['ssr_fraud_checker']))
                @php
                    $ssrFormAction = (string) ($seo['canonical_path'] ?? '/bd-fraud-checker');
                    if ($ssrFormAction === '') {
                        $ssrFormAction = '/bd-fraud-checker';
                    }
                @endphp
                <div class="ssr-fraud-checker" id="ssr-fraud-check">
                    <h2>{{ $isEnPrerender ? 'Free courier fraud check' : 'ফ্রি কুরিয়ার ফ্রড চেক' }}</h2>
                    <p class="ssr-fraud-hint">
                        {{ $isEnPrerender
                            ? 'Enter a Bangladesh mobile number to see Pathao, Steadfast, and RedX delivery history before you confirm COD.'
                            : 'বাংলাদেশি মোবাইল নম্বর দিন — Pathao, Steadfast, RedX হিস্টোরি ও সাকসেস রেট দেখে COD কনফার্ম করুন।' }}
                    </p>
                    <form method="get" action="{{ $ssrFormAction }}#fraud-check" role="search" aria-label="{{ $isEnPrerender ? 'Courier fraud checker' : 'কুরিয়ার ফ্রড চেকার' }}">
                        <label for="ssr-fraud-phone">
                            {{ $isEnPrerender ? 'Mobile number' : 'মোবাইল নম্বর' }}
                            <input
                                id="ssr-fraud-phone"
                                name="phone"
                                type="tel"
                                inputmode="numeric"
                                maxlength="14"
                                placeholder="017XXXXXXXX"
                                autocomplete="tel"
                                required
                                value="{{ request()->query('phone') }}"
                            />
                        </label>
                        <button type="submit">
                            {{ $isEnPrerender ? 'Check fraud' : 'ফ্রড চেক করুন' }}
                        </button>
                    </form>
                </div>
            @endif
            @if (! empty($seo['ssr_calculator']))
                @php
                    $calcType = (string) $seo['ssr_calculator'];
                    $calcAction = (string) ($seo['canonical_path'] ?? '/');
                    $q = request()->query();
                @endphp
                <div class="ssr-fraud-checker" id="ssr-calculator">
                    <h2>
                        @if ($calcType === 'return_loss')
                            {{ $isEnPrerender ? 'Return loss calculator' : 'রিটার্ন লস ক্যালকুলেটর' }}
                        @elseif ($calcType === 'courier_charge')
                            {{ $isEnPrerender ? 'Courier charge calculator' : 'কুরিয়ার চার্জ ক্যালকুলেটর' }}
                        @else
                            {{ $isEnPrerender ? 'Ads ROAS calculator' : 'Ads ROAS ক্যালকুলেটর' }}
                        @endif
                    </h2>
                    <p class="ssr-fraud-hint">
                        {{ $isEnPrerender
                            ? 'Enter your numbers below — the interactive calculator loads after the page opens.'
                            : 'নিচে সংখ্যা দিন — পেজ খুললে ইন্টারঅ্যাকটিভ ক্যালকুলেটর চালু হবে।' }}
                    </p>
                    <form method="get" action="{{ $calcAction }}#calculator" class="ssr-calc-form">
                        @if ($calcType === 'return_loss')
                            <label for="ssr-daily-orders">
                                {{ $isEnPrerender ? 'Daily orders' : 'দৈনিক অর্ডার' }}
                                <input id="ssr-daily-orders" name="daily_orders" type="number" min="1" max="5000" value="{{ $q['daily_orders'] ?? 50 }}" required />
                            </label>
                            <label for="ssr-return-rate">
                                {{ $isEnPrerender ? 'Return rate %' : 'রিটার্ন রেট %' }}
                                <input id="ssr-return-rate" name="return_rate" type="number" min="0" max="100" value="{{ $q['return_rate'] ?? 25 }}" required />
                            </label>
                            <label for="ssr-cost-per-return">
                                {{ $isEnPrerender ? 'Cost per return (৳)' : 'প্রতি রিটার্ন খরচ (৳)' }}
                                <input id="ssr-cost-per-return" name="cost_per_return" type="number" min="0" max="5000" value="{{ $q['cost_per_return'] ?? 120 }}" required />
                            </label>
                        @elseif ($calcType === 'courier_charge')
                            <label for="ssr-weight-kg">
                                {{ $isEnPrerender ? 'Weight (kg)' : 'ওজন (কেজি)' }}
                                <input id="ssr-weight-kg" name="weight_kg" type="number" min="0.1" max="50" step="0.1" value="{{ $q['weight_kg'] ?? 1 }}" required />
                            </label>
                            <label for="ssr-cod-amount">
                                {{ $isEnPrerender ? 'COD amount (৳)' : 'COD অ্যামাউন্ট (৳)' }}
                                <input id="ssr-cod-amount" name="cod_amount" type="number" min="0" max="200000" value="{{ $q['cod_amount'] ?? 0 }}" />
                            </label>
                        @else
                            <label for="ssr-ad-spend">
                                {{ $isEnPrerender ? 'Ad spend (৳)' : 'অ্যাড স্পেন্ড (৳)' }}
                                <input id="ssr-ad-spend" name="ad_spend" type="number" min="0" value="{{ $q['ad_spend'] ?? 50000 }}" required />
                            </label>
                            <label for="ssr-pixel-purchases">
                                {{ $isEnPrerender ? 'Pixel purchases' : 'Pixel Purchase' }}
                                <input id="ssr-pixel-purchases" name="pixel_purchases" type="number" min="0" value="{{ $q['pixel_purchases'] ?? 200 }}" required />
                            </label>
                            <label for="ssr-fake-rate">
                                {{ $isEnPrerender ? 'Fake/cancel %' : 'ফেক/ক্যানসেল %' }}
                                <input id="ssr-fake-rate" name="fake_cancel_rate" type="number" min="0" max="100" value="{{ $q['fake_cancel_rate'] ?? 30 }}" required />
                            </label>
                            <label for="ssr-aov">
                                {{ $isEnPrerender ? 'AOV (৳)' : 'AOV (৳)' }}
                                <input id="ssr-aov" name="aov" type="number" min="0" value="{{ $q['aov'] ?? 1200 }}" required />
                            </label>
                        @endif
                        <button type="submit">
                            {{ $isEnPrerender ? 'Calculate' : 'হিসাব করুন' }}
                        </button>
                    </form>
                </div>
            @endif
            @if (($seo['page_kind'] ?? null) === 'about' && ! empty($seo['author_image']))
                <figure>
                    <img
                        src="{{ $seo['author_image'] }}"
                        alt="{{ ($seo['author_name'] ?? 'Muhibbullah Ansary').' — '.($seo['author_role'] ?? 'Founder & CEO, WPSaleHub') }}"
                        width="1200"
                        height="1200"
                        loading="eager"
                        decoding="async"
                        fetchpriority="high"
                    />
                    <figcaption>{{ $seo['author_name'] ?? 'Muhibbullah Ansary' }} — {{ $seo['author_role'] ?? 'Founder & CEO, WPSaleHub' }}</figcaption>
                </figure>
            @endif
            @if (! empty($seo['content_sections']) && is_array($seo['content_sections']))
                @foreach ($seo['content_sections'] as $section)
                    @if (! empty($section['heading']))
                        <h2>{{ $section['heading'] }}</h2>
                    @endif
                    @foreach (($section['paragraphs'] ?? []) as $paragraph)
                        @if (is_string($paragraph) && $paragraph !== '')
                            <p>{!! \App\Support\SeoPrerenderText::linkifyHtml($paragraph, $isEnPrerender) !!}</p>
                        @endif
                    @endforeach
                    @foreach (($section['figures'] ?? []) as $figure)
                        @if (! empty($figure['src']))
                            <figure>
                                <img
                                    src="{{ $figure['src'] }}"
                                    alt="{{ $figure['alt'] ?? ($figure['caption'] ?? 'Diagram') }}"
                                    loading="lazy"
                                    decoding="async"
                                    width="1200"
                                    height="675"
                                />
                                @if (! empty($figure['caption']))
                                    <figcaption>{{ $figure['caption'] }}</figcaption>
                                @endif
                            </figure>
                        @endif
                    @endforeach
                @endforeach
            @endif
            {{-- Full sitemap link list so crawlers never see orphaned sitemap URLs --}}
            <nav aria-label="Site pages">
                <ul>
                    @foreach (\App\Support\SeoPrerenderText::sitemapNavLinks($isEnPrerender) as $navLink)
                        <li><a href="{{ $navLink['href'] }}">{{ $navLink['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
            @if (! empty($seo['cluster_links']) && is_array($seo['cluster_links']))
                <ul>
                    @foreach ($seo['cluster_links'] as $link)
                        @if (! empty($link['path']) && ! empty($link['label']))
                            <li><a href="{{ $link['path'] }}">{{ $link['label'] }}</a></li>
                        @endif
                    @endforeach
                </ul>
            @endif
            @if (! empty($seo['faqs']) && is_array($seo['faqs']))
                <div>
                    @foreach ($seo['faqs'] as $faq)
                        @if (! empty($faq['q']) && ! empty($faq['a']))
                            <h2>{{ $faq['q'] }}</h2>
                            <p>{!! \App\Support\SeoPrerenderText::linkifyHtml((string) $faq['a'], $isEnPrerender) !!}</p>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
        <noscript>
            <section style="max-width:48rem;margin:0 auto;padding:1.5rem 1rem;color:#e2e8f0;background:#0a0a0a;font-family:system-ui,sans-serif">
                <p style="font-size:1.25rem;font-weight:700;color:#fff">{{ $seo['prerender_h1'] ?? $seo['title'] ?? 'WooEasyLife' }}</p>
                <p>{{ $seo['prerender_lead'] ?? $seo['description'] ?? '' }}</p>
                @if (! empty($seo['ssr_fraud_checker']))
                    <form method="get" action="{{ $seo['canonical_path'] ?? '/bd-fraud-checker' }}#fraud-check" style="margin:1rem 0;display:flex;flex-wrap:wrap;gap:0.75rem">
                        <label for="ssr-fraud-phone-noscript" style="flex:1 1 12rem;color:#e2e8f0;font-size:0.875rem">
                            {{ $isEnPrerender ? 'Mobile number' : 'মোবাইল নম্বর' }}
                            <input id="ssr-fraud-phone-noscript" name="phone" type="tel" inputmode="numeric" maxlength="14" placeholder="017XXXXXXXX" required style="display:block;width:100%;margin-top:0.35rem;padding:0.75rem;border-radius:0.5rem;border:1px solid #334155;background:#111;color:#fff" />
                        </label>
                        <button type="submit" style="padding:0.85rem 1.1rem;border:0;border-radius:0.5rem;background:#f59e0b;color:#000;font-weight:700">
                            {{ $isEnPrerender ? 'Check fraud' : 'ফ্রড চেক করুন' }}
                        </button>
                    </form>
                @endif
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
