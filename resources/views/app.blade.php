<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    <div id="app-loader" aria-live="polite" aria-busy="true">
        <div class="app-loader-spinner" role="status" aria-label="লোড হচ্ছে">
            <div class="app-loader-spinner__ring app-loader-spinner__ring--one"></div>
            <div class="app-loader-spinner__ring app-loader-spinner__ring--two"></div>
            <img src="/app-logo" alt="" class="app-loader-spinner__logo">
        </div>
    </div>

    @inertia
</body>

</html>
