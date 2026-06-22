<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="WooEasyLife mobile app privacy policy for WooCommerce merchants.">
    <title>{{ $title ?? 'WooEasyLife' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --accent: #2563eb;
            --accent-soft: #eff6ff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            min-height: 100vh;
            padding: 2rem 1rem 3rem;
        }

        .container {
            max-width: 760px;
            margin: 0 auto;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            color: var(--muted);
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            text-decoration: none;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 2rem 1.5rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        @media (min-width: 640px) {
            .page { padding: 3rem 1.5rem 4rem; }
            .card { padding: 2.5rem 2.5rem; }
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.25rem;
            margin-bottom: 1.5rem;
            color: var(--muted);
            font-size: 0.875rem;
        }

        .legal-content h1 {
            margin: 0 0 1rem;
            font-size: 1.875rem;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .legal-content h2 {
            margin: 2rem 0 0.75rem;
            font-size: 1.25rem;
            line-height: 1.3;
        }

        .legal-content h3 {
            margin: 1.5rem 0 0.5rem;
            font-size: 1.05rem;
        }

        .legal-content p,
        .legal-content li {
            color: #334155;
        }

        .legal-content p {
            margin: 0.75rem 0;
        }

        .legal-content ul,
        .legal-content ol {
            margin: 0.75rem 0;
            padding-left: 1.25rem;
        }

        .legal-content li + li {
            margin-top: 0.35rem;
        }

        .legal-content hr {
            border: 0;
            border-top: 1px solid var(--border);
            margin: 2rem 0;
        }

        .legal-content a {
            color: var(--accent);
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .legal-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 0.925rem;
        }

        .legal-content th,
        .legal-content td {
            border: 1px solid var(--border);
            padding: 0.65rem 0.75rem;
            text-align: left;
            vertical-align: top;
        }

        .legal-content th {
            background: var(--accent-soft);
            font-weight: 600;
        }

        .legal-content strong {
            color: var(--text);
        }

        .footer {
            margin-top: 1.5rem;
            color: var(--muted);
            font-size: 0.875rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="container">
            <a class="brand" href="https://wpsalehub.com">WooEasyLife · WPSaleHub</a>
            <div class="card">
                @yield('content')
            </div>
            <p class="footer">© {{ date('Y') }} WPSaleHub. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
