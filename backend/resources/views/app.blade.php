<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        @php
            $seo = \App\Support\Seo::forRequest(request());
            $gaId = config('seo.ga_measurement_id') ?: config('services.google.analytics_id');
        @endphp

        @if (config('features.analytics') && filled($gaId))
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($gaId), {
                send_page_view: true,
                anonymize_ip: true
            });
        </script>
        @endif

        @if (! empty($seo['title']))
            <title>{{ $seo['title'] }}</title>
            @include('partials.seo-head', ['seo' => $seo])
        @else
            <title inertia>{{ config('seo.site_name', config('app.name', 'Fuel Prices PK')) }}</title>
        @endif

        <meta name="vapid-key" content="{{ config('services.webpush.public_key') }}">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/icons/icon-192.png" type="image/png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link
            rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=IBM+Plex+Mono:wght@500;600&family=IBM+Plex+Sans:wght@400;500;600&family=Noto+Nastaliq+Urdu:wght@400;500;600;700&display=swap"
        >
        @inertiaHead
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body class="font-sans antialiased" style="margin:0;background:#0b0f14;min-height:100%;-webkit-text-size-adjust:100%;touch-action:manipulation">
        @if (! empty($seo['h1']) && ! str_contains((string) ($seo['robots'] ?? ''), 'noindex'))
            <div id="firstPaintSeo" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;">
                <h1>{{ $seo['h1'] }}</h1>
                @if (! empty($seo['description']))
                    <p>{{ $seo['description'] }}</p>
                @endif
            </div>
        @endif
        @inertia
        <script>
            (function () {
                function removeSeo() {
                    var el = document.getElementById('firstPaintSeo');
                    if (el && el.parentNode) el.parentNode.removeChild(el);
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', removeSeo);
                } else {
                    removeSeo();
                }
                setTimeout(removeSeo, 2500);
            })();
        </script>
    </body>
</html>
