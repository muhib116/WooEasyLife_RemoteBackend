<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription ?? 'Legal information from WPSaleHub.' }}">
    @if (! empty($robots))
        <meta name="robots" content="{{ $robots }}">
    @endif
    <title>{{ $title ?? 'WPSaleHub' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --bg-top: #0f172a;
            --bg-bottom: #f1f5f9;
            --surface: #ffffff;
            --text: #0f172a;
            --text-body: #475569;
            --muted: #64748b;
            --border: #e2e8f0;
            --accent: #4f46e5;
            --accent-hover: #4338ca;
            --accent-soft: #eef2ff;
            --accent-ring: rgba(79, 70, 229, 0.18);
            --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.05);
            --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.08);
            --radius: 1rem;
            --radius-lg: 1.25rem;
            --header-h: 4rem;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: "Plus Jakarta Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg-bottom);
            color: var(--text);
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        a { color: inherit; }

        /* ── Header ── */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 50;
            height: var(--header-h);
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .site-header__inner {
            max-width: 52rem;
            margin: 0 auto;
            height: 100%;
            padding: 0 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            text-decoration: none;
            color: #f8fafc;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: -0.01em;
        }

        .brand__mark {
            width: 2rem;
            height: 2rem;
            border-radius: 0.55rem;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            display: grid;
            place-items: center;
            font-size: 0.7rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.45);
        }

        .brand__sub {
            display: block;
            font-size: 0.7rem;
            font-weight: 500;
            color: #94a3b8;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .header-link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s;
            white-space: nowrap;
        }

        .header-link:hover { background: rgba(255, 255, 255, 0.14); }

        /* ── Page shell ── */
        .page-hero {
            background: linear-gradient(180deg, var(--bg-top) 0%, #1e293b 55%, var(--bg-bottom) 100%);
            padding: 2.5rem 1.25rem 0;
        }

        .page-hero__inner {
            max-width: 52rem;
            margin: 0 auto;
        }

        .page-hero__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            background: rgba(99, 102, 241, 0.2);
            border: 1px solid rgba(129, 140, 248, 0.35);
            color: #c7d2fe;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .page-hero__title {
            margin: 0 0 1rem;
            font-size: clamp(1.75rem, 5vw, 2.5rem);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.03em;
            color: #f8fafc;
        }

        .page-hero__lead {
            margin: 0 0 1.5rem;
            max-width: 38rem;
            color: #94a3b8;
            font-size: clamp(0.95rem, 2.5vw, 1.05rem);
            line-height: 1.6;
        }

        .meta-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .meta-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.85rem;
            border-radius: 0.6rem;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .meta-badge strong { color: #f1f5f9; font-weight: 600; }

        /* ── Main card ── */
        .page-body {
            padding: 0 1.25rem 3rem;
            margin-top: -1.5rem;
        }

        .page-body__inner {
            max-width: 52rem;
            margin: 0 auto;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem 1.25rem;
            box-shadow: var(--shadow-md);
        }

        @media (min-width: 640px) {
            .page-hero { padding: 3rem 1.5rem 0; }
            .page-body { padding: 0 1.5rem 4rem; margin-top: -2rem; }
            .card { padding: 2.25rem 2.5rem; }
            .site-header__inner { padding: 0 1.5rem; }
        }

        /* ── Legal prose ── */
        .legal-content h1 { display: none; }

        .legal-content h2 {
            margin: 2.25rem 0 0.85rem;
            padding-top: 0.25rem;
            font-size: clamp(1.05rem, 3vw, 1.2rem);
            font-weight: 700;
            line-height: 1.35;
            letter-spacing: -0.02em;
            color: var(--text);
            scroll-margin-top: calc(var(--header-h) + 1rem);
        }

        .legal-content h2:first-child { margin-top: 0; }

        .legal-content h3 {
            margin: 1.5rem 0 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text);
        }

        .legal-content p,
        .legal-content li { color: var(--text-body); }

        .legal-content p { margin: 0.8rem 0; }

        .legal-content ul,
        .legal-content ol {
            margin: 0.8rem 0;
            padding-left: 1.35rem;
        }

        .legal-content li + li { margin-top: 0.4rem; }

        .legal-content li::marker { color: var(--accent); }

        .legal-content hr {
            border: 0;
            border-top: 1px solid var(--border);
            margin: 2rem 0;
        }

        .legal-content a {
            color: var(--accent);
            font-weight: 500;
            text-decoration: underline;
            text-underline-offset: 3px;
            word-break: break-word;
        }

        .legal-content a:hover { color: var(--accent-hover); }

        .legal-content strong { color: var(--text); font-weight: 600; }

        .legal-content code {
            font-size: 0.875em;
            background: #f1f5f9;
            border: 1px solid var(--border);
            border-radius: 0.35rem;
            padding: 0.1em 0.4em;
            word-break: break-all;
        }

        /* Responsive table */
        .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 1rem 0;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
        }

        .legal-content table {
            width: 100%;
            min-width: 28rem;
            border-collapse: collapse;
            margin: 0;
            font-size: 0.875rem;
        }

        .legal-content th,
        .legal-content td {
            padding: 0.75rem 1rem;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid var(--border);
        }

        .legal-content tr:last-child td,
        .legal-content tr:last-child th { border-bottom: 0; }

        .legal-content th {
            background: var(--accent-soft);
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
        }

        /* ── Contact card ── */
        .contact-card {
            margin-top: 2.5rem;
            padding: 1.25rem;
            border-radius: var(--radius);
            background: linear-gradient(135deg, var(--accent-soft), #f8fafc);
            border: 1px solid #c7d2fe;
        }

        .contact-card__title {
            margin: 0 0 0.35rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text);
        }

        .contact-card__sub {
            margin: 0 0 1rem;
            font-size: 0.85rem;
            color: var(--muted);
        }

        .contact-links {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        @media (min-width: 480px) {
            .contact-links {
                flex-direction: row;
                flex-wrap: wrap;
            }
        }

        .contact-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            border-radius: 0.65rem;
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
            transition: border-color 0.15s, box-shadow 0.15s;
            word-break: break-all;
        }

        .contact-link:hover {
            border-color: #a5b4fc;
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        .contact-link__icon {
            width: 1.1rem;
            height: 1.1rem;
            flex-shrink: 0;
            color: var(--accent);
        }

        /* ── Footer ── */
        .site-footer {
            max-width: 52rem;
            margin: 0 auto;
            padding: 0 1.25rem 2.5rem;
            text-align: center;
            color: var(--muted);
            font-size: 0.8rem;
        }

        .site-footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }

        .site-footer a:hover { text-decoration: underline; }

        @media print {
            .site-header, .page-hero, .contact-card, .site-footer { display: none; }
            .page-body { margin: 0; padding: 0; }
            .card { box-shadow: none; border: none; padding: 0; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="{{ $contactWebsite ?? 'https://app.wpsalehub.com' }}">
                <span class="brand__mark">{{ $brandMark ?? 'WE' }}</span>
                <span>
                    {{ $brandName ?? 'WooEasyLife' }}
                    <span class="brand__sub">by WPSaleHub</span>
                </span>
            </a>

            <a class="header-link" href="{{ $contactWebsite ?? 'https://app.wpsalehub.com' }}">
                {{ ($brandName ?? 'WooEasyLife') }} home ↗
            </a>
        </div>
    </header>

    @yield('hero')

    <main class="page-body">
        <div class="page-body__inner">
            <div class="card">
                @yield('content')
            </div>
        </div>
    </main>

    <footer class="site-footer">
        © {{ date('Y') }} WPSaleHub ·
        <a href="{{ $contactWebsite ?? 'https://app.wpsalehub.com' }}">{{ ($brandName ?? 'WooEasyLife') }} home</a>
    </footer>

    <script>
        document.querySelectorAll('.legal-content table').forEach(function (table) {
            var wrap = document.createElement('div');
            wrap.className = 'table-scroll';
            table.parentNode.insertBefore(wrap, table);
            wrap.appendChild(table);
        });
    </script>
</body>
</html>
