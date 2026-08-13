<?php

/**
 * Public marketing site.
 *
 * Every commercial fact the site states about Nacre lives here, so a price
 * change or a store going live is a one-line edit rather than a hunt through
 * Blade templates. The page graph (which pages exist, and their slug in each
 * language) is a separate concern and lives in `App\Support\SiteMap`.
 */
return [

    /*
    |---------------------------------------------------------------------------
    | Canonical origin
    |---------------------------------------------------------------------------
    |
    | Deliberately NOT `app.url`. The shipped mobile binaries hardcode
    | `https://thequesting.app/api`, so that host has to keep answering the API
    | for as long as those builds are in the wild — while the public site is
    | branded Nacre and lives on its own domain. Canonical URLs, hreflang, the
    | sitemap, and og:url all read this value; `app.url` keeps serving the API.
    |
    | Both hostnames can point at this same application: `routes/web.php`
    | redirects the site's routes to this origin, and leaves `/api` alone.
    |
    */

    'url' => rtrim((string) env('SITE_URL', 'https://thenacre.app'), '/'),

    /*
    |---------------------------------------------------------------------------
    | Hostnames to fold into the canonical one
    |---------------------------------------------------------------------------
    |
    | A safe request for a page on any of these hosts is 301'd to `url` above,
    | path and query preserved, by `App\Http\Middleware\RedirectLegacyHost`.
    | `/api` and `/up` are always left alone — shipped app binaries address the
    | API by the old hostname and cannot be updated.
    |
    | An explicit allowlist rather than "anything that isn't the canonical host",
    | so local development, tests and preview deployments need no special case.
    |
    */

    'legacy_hosts' => array_filter(explode(',', (string) env(
        'SITE_LEGACY_HOSTS',
        'thequesting.app,www.thequesting.app,www.thenacre.app',
    ))),

    /*
    |---------------------------------------------------------------------------
    | Languages
    |---------------------------------------------------------------------------
    |
    | French is the primary market (native French copy is the only structural
    | advantage this app has, and French acquisition costs roughly half the US
    | equivalent), so French sits at the root and English under `/en`.
    |
    | `x_default` is English on purpose: hreflang's x-default is what search
    | engines serve to a visitor whose language matches neither, and English is
    | the better fallback for that person than French.
    |
    */

    'locales' => ['fr', 'en'],
    'default_locale' => 'fr',
    'x_default_locale' => 'en',

    /*
    |---------------------------------------------------------------------------
    | App stores
    |---------------------------------------------------------------------------
    |
    | Set `url` to null while a store listing is not publicly reachable: the
    | download buttons render as a disabled "coming soon" badge instead of a
    | dead link, and the store is left out of the structured data. Filling the
    | URL in is the only change needed to activate it.
    |
    | Both stores are live in production since 2026-08-11, on 1.0.4. The
    | "coming soon" branch is kept because it costs nothing and is what a future
    | platform would reuse, but no configured store goes through it today.
    |
    */

    'stores' => [

        'ios' => [
            'apple_id' => '6775552461',
            'url' => env('SITE_APP_STORE_URL', 'https://apps.apple.com/app/id6775552461'),
            'min_os' => 'iOS 15.1',
        ],

        'android' => [
            'package' => 'com.affiniteam.quest',
            'url' => env('SITE_PLAY_STORE_URL', 'https://play.google.com/store/apps/details?id=com.affiniteam.quest'),
            'min_os' => 'Android 7.0',
        ],

        /*
         * Shown next to the App Store button while the store still displays the
         * old name, so a visitor who taps through isn't surprised. Now null: the
         * rename landed and the listing reads "Nacre : Journal, carnet intime",
         * so the note would be claiming something untrue. The wiring stays for
         * the next rename.
         */
        'previous_name' => env('SITE_PREVIOUS_APP_NAME'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Nacre Plus
    |---------------------------------------------------------------------------
    |
    | The store-facing prices of the two auto-renewing subscriptions configured
    | in RevenueCat. Amounts are in `currency` units; everything derived from
    | them (the per-month equivalent of the yearly plan, the saving it
    | represents) is computed in `App\Support\Plus` so the numbers on the page
    | can never contradict each other.
    |
    | `free_media_quota_mb` mirrors `quest.free_media_quota_mb`, which is what
    | the backend actually enforces on a free account's media backups.
    |
    */

    'plus' => [
        'currency' => 'EUR',
        'currency_symbol' => '€',
        'monthly' => 6.99,
        'annual' => 44.99,
        'free_media_quota_mb' => (int) env('QUEST_FREE_MEDIA_QUOTA_MB', 500),
    ],

    /*
    |---------------------------------------------------------------------------
    | Product facts quoted by the copy
    |---------------------------------------------------------------------------
    |
    | Numbers that appear in prose and would otherwise be retyped (and drift)
    | across several pages.
    |
    */

    'facts' => [
        'themes_total' => 7,
        'themes_free' => 3,
        'fonts' => 3,
        'trash_retention_days' => 30,
        'export_formats' => ['Markdown', 'JSON', 'TXT'],
        'ai_provider' => 'Anthropic',
    ],

];
