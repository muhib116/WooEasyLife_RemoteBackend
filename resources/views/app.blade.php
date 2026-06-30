<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

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
    </style>
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    <div id="app-loader" aria-live="polite" aria-busy="true">
        <div class="app-loader-spinner" role="status" aria-label="লোড হচ্ছে">
            <div class="app-loader-spinner__ring app-loader-spinner__ring--one"></div>
            <div class="app-loader-spinner__ring app-loader-spinner__ring--two"></div>
            <img src="/app-logo" alt="" class="app-loader-spinner__logo" width="53" height="53">
        </div>
    </div>

    @inertia
</body>

</html>
