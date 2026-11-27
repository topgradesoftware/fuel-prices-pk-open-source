@php
    /** @var array<string, mixed> $seo */
    $og = $seo['og'] ?? [];
    $ogImage = is_array($og['image'] ?? null) ? $og['image'] : \App\Support\Seo::ogImage();
    $twitter = $seo['twitter'] ?? [];
    $verification = config('seo.google_site_verification');
    $bingVerification = config('seo.bing_site_verification');
    $canonical = $seo['canonical'] ?? url()->current();
    $locale = \App\Support\Seo::locale();
    $geo = config('seo.geo', []);
@endphp
@if (! empty($seo['description']))
<meta name="description" content="{{ $seo['description'] }}">
@endif
@if (! empty($seo['keywords']))
<meta name="keywords" content="{{ $seo['keywords'] }}">
@endif
@if (! empty($seo['robots']))
<meta name="robots" content="{{ $seo['robots'] }}">
@endif
@if (! empty($seo['canonical']))
<link rel="canonical" href="{{ $seo['canonical'] }}">
@endif
<link rel="alternate" hreflang="{{ \App\Support\Seo::hreflang() }}" href="{{ $canonical }}">
<link rel="alternate" hreflang="x-default" href="{{ $canonical }}">
@if (! empty($geo['region']))
<meta name="geo.region" content="{{ $geo['region'] }}">
@endif
@if (! empty($geo['placename']))
<meta name="geo.placename" content="{{ $geo['placename'] }}">
@endif
@if ($verification)
<meta name="google-site-verification" content="{{ $verification }}">
@endif
@if ($bingVerification)
<meta name="msvalidate.01" content="{{ $bingVerification }}">
@endif
<meta name="author" content="{{ config('seo.author', 'TopGrade Software') }}">
<meta name="application-name" content="{{ $seo['site_name'] ?? config('seo.site_name') }}">
<meta name="theme-color" content="{{ config('seo.theme_color', '#f2a63f') }}">
<meta name="apple-mobile-web-app-title" content="{{ $seo['site_name'] ?? config('seo.site_name') }}">
<meta property="og:type" content="{{ $og['type'] ?? 'website' }}">
<meta property="og:site_name" content="{{ $og['site_name'] ?? ($seo['site_name'] ?? config('seo.site_name')) }}">
<meta property="og:title" content="{{ $og['title'] ?? ($seo['title'] ?? '') }}">
<meta property="og:description" content="{{ $og['description'] ?? ($seo['description'] ?? '') }}">
<meta property="og:url" content="{{ $og['url'] ?? ($seo['canonical'] ?? url()->current()) }}">
<meta property="og:locale" content="{{ $og['locale'] ?? $locale }}">
<meta property="og:image" content="{{ $ogImage['url'] }}">
<meta property="og:image:secure_url" content="{{ $ogImage['url'] }}">
<meta property="og:image:width" content="{{ $ogImage['width'] }}">
<meta property="og:image:height" content="{{ $ogImage['height'] }}">
<meta property="og:image:type" content="{{ $ogImage['type'] }}">
<meta property="og:image:alt" content="{{ $ogImage['alt'] }}">
<meta name="twitter:card" content="{{ $twitter['card'] ?? 'summary_large_image' }}">
<meta name="twitter:title" content="{{ $twitter['title'] ?? ($seo['title'] ?? '') }}">
<meta name="twitter:description" content="{{ $twitter['description'] ?? ($seo['description'] ?? '') }}">
<meta name="twitter:image" content="{{ $twitter['image'] ?? $ogImage['url'] }}">
@foreach (($seo['json_ld'] ?? []) as $block)
<script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
