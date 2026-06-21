<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <meta name="theme-color" content="#1F4E79">

        {{-- Подтверждение прав в Яндекс.Вебмастере. --}}
        <meta name="yandex-verification" content="451d14762ac81dea" />

        {{-- Тема ставится до рендера, чтобы не было мигания. --}}
        <script>
            (function () {
                try {
                    var t = localStorage.getItem('theme');
                    if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>

        <title inertia>{{ $metaTitle ?? config('app.name', 'Отклик') }}</title>

        {{-- SEO-мета для публичных страниц (рендерим на сервере: SPA-Head без SSR
             роботы не видят). Передаются контроллером через withViewData. --}}
        @isset($metaDescription)
            <meta name="description" content="{{ $metaDescription }}">
        @endisset
        @isset($metaKeywords)
            <meta name="keywords" content="{{ $metaKeywords }}">
        @endisset
        @isset($metaCanonical)
            <link rel="canonical" href="{{ $metaCanonical }}">
            <meta property="og:url" content="{{ $metaCanonical }}">
        @endisset
        @isset($metaTitle)
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="Отклик">
            <meta property="og:title" content="{{ $metaOgTitle ?? $metaTitle }}">
            <meta property="og:image" content="{{ $metaOgImage ?? (rtrim(config('app.url'), '/') . '/apple-touch-icon.png') }}">
            <meta name="twitter:card" content="summary_large_image">
            @isset($metaDescription)
                <meta property="og:description" content="{{ $metaOgDescription ?? $metaDescription }}">
            @endisset
        @endisset

        @isset($siteJsonLd)
            <script type="application/ld+json">{!! $siteJsonLd !!}</script>
        @endisset

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- Чат-виджет «Отклик» на нашем публичном сайте (бот отвечает по нашей же
             базе знаний). Грузим только на маркетинг-домене, не в кабинете. --}}
        @if (request()->getHost() === config('app.marketing_domain'))
            <script src="https://business.otcl1ck.ru/widget/v1/widget.js" data-otklik-tenant="019eeb78-b906-7061-a9ef-11925dcf3215" data-otklik-channel="019eeb78-bb19-7008-a80b-bf676ba05630" defer></script>
        @endif
    </body>
</html>
