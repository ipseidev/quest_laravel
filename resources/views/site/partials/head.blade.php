{{--
    The <head> for every public page — the marketing site and the legal pages both.

    Shared rather than duplicated so the two cannot drift: a legal page gets the same
    canonical handling, the same hreflang reciprocity and the same social cards as a
    landing page, and there is one place to change when a rule changes.

    Expects, from whichever controller rendered the page: $locale, $meta
    (title + description), $canonical, $alternates.
--}}
@php
    $stores = config('site.stores');
    $ogImage = config('site.url').'/img/og-'.$locale.'.png';
    $xDefault = config('site.x_default_locale');
    $ogLocale = fn (string $l): string => $l === 'fr' ? 'fr_FR' : 'en_GB';
@endphp

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>{{ $meta['title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}">

{{-- max-image-preview:large is what lets the screenshots show full size in Google's
     results, which matters a lot for an app listing. --}}
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">

<link rel="canonical" href="{{ $canonical }}">

{{-- Every language version lists every language version, itself included: that
     reciprocity is what Google requires for hreflang to be honoured. --}}
@foreach ($alternates as $altLocale => $altUrl)
    <link rel="alternate" hreflang="{{ $altLocale }}" href="{{ $altUrl }}">
@endforeach
@isset($alternates[$xDefault])
    <link rel="alternate" hreflang="x-default" href="{{ $alternates[$xDefault] }}">
@endisset

{{-- Fonts are same-origin, so preloading them removes a full round trip from the
     point where the first heading can paint. --}}
<link rel="preload" href="/fonts/lora-600.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/fonts/lora-500.woff2" as="font" type="font/woff2" crossorigin>

<meta name="theme-color" content="#0f0e17">
<meta name="color-scheme" content="dark">
{{-- Stops iOS from turning figures in the pricing table into phone links. --}}
<meta name="format-detection" content="telephone=no">

<meta property="og:type" content="website">
<meta property="og:site_name" content="Nacre">
<meta property="og:locale" content="{{ $ogLocale($locale) }}">
@foreach ($alternates as $altLocale => $altUrl)
    @if ($altLocale !== $locale)
        <meta property="og:locale:alternate" content="{{ $ogLocale($altLocale) }}">
    @endif
@endforeach
<meta property="og:title" content="{{ $meta['title'] }}">
<meta property="og:description" content="{{ $meta['description'] }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ __('marketing.common.og_alt') }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $meta['title'] }}">
<meta name="twitter:description" content="{{ $meta['description'] }}">
<meta name="twitter:image" content="{{ $ogImage }}">

{{-- Safari on iOS turns this into the Smart App Banner, which converts far better
     than any button we could draw. --}}
@if ($stores['ios']['url'])
    <meta name="apple-itunes-app" content="app-id={{ $stores['ios']['apple_id'] }}">
@endif

<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="/img/icon-32.png" type="image/png" sizes="32x32">
<link rel="icon" href="/img/icon-192.png" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="/img/apple-touch-icon.png">

@vite(['resources/css/app.css', 'resources/js/app.js'])

@include('site.partials.jsonld')
