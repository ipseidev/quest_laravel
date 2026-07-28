{{--
    The layout for every marketing page.

    Pages `@extends` this and fill `@section('content')`. Everything in the head is
    derived from data the controller already assembled ($meta, $canonical,
    $alternates, $locale, $breadcrumbs), so a page template never restates an SEO
    fact — which is how a canonical URL and an og:url drift apart.
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    @include('site.partials.head')
</head>
<body class="bg-ink text-paper antialiased">
    <a href="#main"
       class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-paper focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-ink">
        {{ __('marketing.common.skip_to_content') }}
    </a>

    @include('site.partials.header')

    <main id="main">
        @yield('content')
    </main>

    @include('site.partials.footer')
</body>
</html>
