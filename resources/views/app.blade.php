<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Defaults — Inertia <Head> on each page overrides these via @inertiaHead. --}}
        <title inertia>{{ config('app.name', 'AuctionBall') }} — Real-time cricket & football auction software</title>
        <meta name="description"
              content="Run live player auctions for cricket and football tournaments. Real-time bidding, big-screen broadcast, multi-team budgets, and bKash/PayPal billing — built for Bangladesh."
              inertia>

        <meta name="theme-color" content="#6366f1">
        <meta name="color-scheme" content="light">
        <meta name="application-name" content="AuctionBall">
        <meta name="apple-mobile-web-app-title" content="AuctionBall">

        {{-- Favicon set — uses appLogo if uploaded, falls back to generic / svg. --}}
        @php
            $platformSettings = \App\Models\PlatformSettings::current();
            $logo = $platformSettings->app_logo_url ?? null;
        @endphp
        @if ($logo)
            <link rel="icon" type="image/png" href="{{ $logo }}">
            <link rel="apple-touch-icon" href="{{ $logo }}">
        @else
            <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        @endif

        {{-- Open Graph defaults; per-page <Head> in Vue overrides title/description/image. --}}
        <meta property="og:site_name" content="AuctionBall" inertia>
        <meta property="og:type" content="website" inertia>
        <meta property="og:locale" content="{{ app()->getLocale() === 'bn' ? 'bn_BD' : 'en_US' }}" inertia>

        <meta name="twitter:card" content="summary_large_image" inertia>
        <meta name="twitter:site" content="@auctionball" inertia>

        {{-- Robots default — crawler/indexer hint; per-page <Head> can flip to noindex on private routes. --}}
        <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1" inertia>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Anek+Bangla:wght@100..800&family=Mona+Sans:ital,wght@0,200..900;1,200..900&family=JetBrains+Mono:wght@400;500&display=swap"
            rel="preload"
            as="style"
            onload="this.onload=null;this.rel='stylesheet'"
        />
        <noscript>
            <link
                href="https://fonts.googleapis.com/css2?family=Anek+Bangla:wght@100..800&family=Mona+Sans:ital,wght@0,200..900;1,200..900&family=JetBrains+Mono:wght@400;500&display=swap"
                rel="stylesheet"
            />
        </noscript>

        @routes
        @vite('resources/js/app.js')
        @inertiaHead
        {!! $platformSettings->head_scripts !!}
    </head>
    <body class="font-sans antialiased text-ink-900">
        {!! $platformSettings->body_start_scripts !!}
        @inertia
        {!! $platformSettings->body_end_scripts !!}
    </body>
</html>
