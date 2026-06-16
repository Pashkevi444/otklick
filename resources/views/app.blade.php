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

        <title inertia>{{ config('app.name', 'Отклик') }}</title>

        @isset($siteJsonLd)
            <script type="application/ld+json">{!! $siteJsonLd !!}</script>
        @endisset

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        {{-- TODO(временно): тестовый виджет «Отклик» на нашем публичном сайте. УДАЛИТЬ после теста. --}}
        @if (request()->getHost() === config('app.marketing_domain'))
            <script src="https://business.otcl1ck.ru/widget/v1/widget.js" data-otklik-tenant="019ecbf6-1101-7114-98ba-16c5fbbbc189" data-otklik-channel="019ed026-baa4-73d4-a930-c045cc75c28f" defer></script>
        @endif
    </body>
</html>
