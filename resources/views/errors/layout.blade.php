{{--
    Error pages.

    Standalone rather than extending site.layout: that layout needs the SEO payload
    the site controller assembles ($canonical, $alternates, $breadcrumbs), and an
    error page is exactly the situation where that data is absent. A layout that can
    throw is the wrong thing to render when something has already gone wrong.

    The language is inferred from the URL prefix — the only signal available here,
    since no route matched.
--}}
@php
    $locale = \App\Support\SiteMap::requestLocale();
    $home = \App\Support\SiteMap::path('home', $locale);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Nacre</title>
    {{-- An error page must never be indexed. --}}
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#0f0e17">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="apple-touch-icon" href="/img/apple-touch-icon.png">
    @vite(['resources/css/app.css'])
</head>
<body class="night-sky flex min-h-screen flex-col items-center justify-center px-6 text-center">
    <div aria-hidden="true" class="starfield"></div>

    <div class="relative">
        <img src="/img/pearl-240.png" alt="" width="96" height="96" class="mx-auto mb-9 size-24" aria-hidden="true">

        <p class="mb-3 text-sm font-semibold tracking-[0.12em] text-accent-soft uppercase">@yield('code')</p>
        <h1 class="text-title text-balance">@yield('heading')</h1>
        <p class="mx-auto mt-4 max-w-md text-muted">@yield('body')</p>

        <p class="mt-9">
            <x-site.button :href="$home">
                {{ $locale === 'en' ? 'Back to the home page' : 'Revenir à l’accueil' }}
            </x-site.button>
        </p>
    </div>
</body>
</html>
