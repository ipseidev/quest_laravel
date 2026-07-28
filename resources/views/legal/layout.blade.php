{{--
    Layout for the four legal pages (privacy policy, terms, support, legal notice).

    Rewritten to sit on the site's own chrome — same head, same header, same footer —
    so a visitor who clicks "Confidentialité" does not land on what looks like a
    different website. The legal *wording* is untouched: those documents were drafted
    to be relied on, and this change is presentation only.

    The page content is authored as prose, so it is wrapped in `prose-nacre` rather
    than being littered with utility classes. `$lang` is kept as the variable name the
    four content views already use; `$locale` is its site-layout counterpart and holds
    the same value.

    These URLs are frozen: /privacy and /support were filed in App Store Connect and
    the app's About screen links to /privacy, /terms and /legal-notice. The language
    is therefore a `?lang=` query, not a path prefix, and `App\Support\SiteMap` builds
    the alternates accordingly.
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
        @include('site.partials.breadcrumbs')

        <div class="container-page py-12 sm:py-16">
            <article class="prose-nacre mx-auto max-w-3xl">
                @yield('content')
            </article>
        </div>
    </main>

    @include('site.partials.footer')
</body>
</html>
