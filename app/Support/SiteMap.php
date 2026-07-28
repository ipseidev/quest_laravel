<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * The public site's page graph: which pages exist, what each one is called in
 * each language, and how they nest.
 *
 * One registry drives four things that are otherwise easy to let drift apart —
 * the routes, the `hreflang` alternates, `sitemap.xml`, and the breadcrumb
 * trail. Adding a page means adding one entry here plus its view and its copy;
 * forget the copy and the test suite fails on the missing translation key.
 *
 * Slugs are stored bare, without a language prefix. `path()` adds the `/en`
 * prefix for the non-default language, so the French slug and the English slug
 * can differ (they should — a French visitor searches "tarifs", not "pricing").
 */
final class SiteMap
{
    /**
     * `null` slug means "this page does not exist in that language".
     *
     * @var array<string, array{
     *     view: string,
     *     slugs: array<string, string|null>,
     *     parent?: string,
     *     priority?: string,
     *     changefreq?: string,
     *     shot?: string,
     *     locale_strategy?: 'path'|'query',
     *     routed?: bool,
     * }>
     */
    private const PAGES = [

        'home' => [
            'view' => 'site.pages.home',
            'slugs' => ['fr' => '', 'en' => ''],
            'priority' => '1.0',
            'changefreq' => 'weekly',
        ],

        'features' => [
            'view' => 'site.pages.features',
            'slugs' => ['fr' => 'fonctionnalites', 'en' => 'features'],
            'priority' => '0.9',
        ],

        // The four product pillars share one view; what differs between them is
        // copy and screenshots, both looked up from the page key.
        'features.quests' => [
            'view' => 'site.pages.feature',
            'slugs' => ['fr' => 'fonctionnalites/quetes', 'en' => 'features/quests'],
            'parent' => 'features',
            'shot' => 'quests',
            'priority' => '0.8',
        ],

        'features.people' => [
            'view' => 'site.pages.feature',
            'slugs' => ['fr' => 'fonctionnalites/personnes', 'en' => 'features/people'],
            'parent' => 'features',
            'shot' => 'person',
            'priority' => '0.8',
        ],

        'features.chapters' => [
            'view' => 'site.pages.feature',
            'slugs' => ['fr' => 'fonctionnalites/chapitres', 'en' => 'features/chapters'],
            'parent' => 'features',
            'shot' => 'chapters',
            'priority' => '0.8',
        ],

        'features.constellation' => [
            'view' => 'site.pages.feature',
            'slugs' => ['fr' => 'fonctionnalites/constellation', 'en' => 'features/constellation'],
            'parent' => 'features',
            'shot' => 'constellation',
            'priority' => '0.8',
        ],

        'privacy' => [
            'view' => 'site.pages.privacy',
            'slugs' => ['fr' => 'vie-privee', 'en' => 'privacy-first'],
            'shot' => 'lock',
            'priority' => '0.8',
        ],

        'pricing' => [
            'view' => 'site.pages.pricing',
            'slugs' => ['fr' => 'tarifs', 'en' => 'pricing'],
            'shot' => 'themes',
            'priority' => '0.9',
        ],

        'faq' => [
            'view' => 'site.pages.faq',
            'slugs' => ['fr' => 'faq', 'en' => 'faq'],
            'priority' => '0.7',
        ],

        'about' => [
            'view' => 'site.pages.about',
            'slugs' => ['fr' => 'a-propos', 'en' => 'about'],
            'priority' => '0.6',
        ],

        'press' => [
            'view' => 'site.pages.press',
            'slugs' => ['fr' => 'presse', 'en' => 'press'],
            'priority' => '0.5',
        ],

        'download' => [
            'view' => 'site.pages.download',
            'slugs' => ['fr' => 'telecharger', 'en' => 'download'],
            'priority' => '0.7',
        ],

        /*
         * The legal pages predate the site and are served by LegalController at
         * fixed root paths with `?lang=` — those exact URLs were submitted to
         * App Store Connect and are linked from the app's About screen, so they
         * cannot move. They are listed here anyway so the sitemap and the footer
         * are built from one registry; `routed: false` keeps this class from
         * trying to register a second route for them.
         */
        'legal.privacy' => [
            'view' => 'legal.privacy',
            'slugs' => ['fr' => 'privacy', 'en' => 'privacy'],
            'locale_strategy' => 'query',
            'routed' => false,
            'priority' => '0.4',
            'changefreq' => 'yearly',
        ],

        'legal.terms' => [
            'view' => 'legal.terms',
            'slugs' => ['fr' => 'terms', 'en' => 'terms'],
            'locale_strategy' => 'query',
            'routed' => false,
            'priority' => '0.4',
            'changefreq' => 'yearly',
        ],

        'legal.support' => [
            'view' => 'legal.support',
            'slugs' => ['fr' => 'support', 'en' => 'support'],
            'locale_strategy' => 'query',
            'routed' => false,
            'priority' => '0.5',
            'changefreq' => 'yearly',
        ],

        'legal.notice' => [
            'view' => 'legal.notice',
            'slugs' => ['fr' => 'legal-notice', 'en' => 'legal-notice'],
            'locale_strategy' => 'query',
            'routed' => false,
            'priority' => '0.3',
            'changefreq' => 'yearly',
        ],
    ];

    /** @return array<string, array<string, mixed>> */
    public static function pages(): array
    {
        return self::PAGES;
    }

    /** Page keys the site registers its own routes for. */
    public static function routedKeys(): array
    {
        return array_keys(array_filter(
            self::PAGES,
            fn (array $page): bool => $page['routed'] ?? true,
        ));
    }

    public static function has(string $key): bool
    {
        return isset(self::PAGES[$key]);
    }

    /** @return array<string, mixed> */
    public static function page(string $key): array
    {
        if (! isset(self::PAGES[$key])) {
            throw new InvalidArgumentException("Unknown site page [{$key}].");
        }

        return self::PAGES[$key];
    }

    public static function view(string $key): string
    {
        return self::page($key)['view'];
    }

    /** Languages a given page is published in. */
    public static function localesFor(string $key): array
    {
        return array_keys(array_filter(
            self::page($key)['slugs'],
            fn (?string $slug): bool => $slug !== null,
        ));
    }

    /**
     * Root-relative path, always with a leading slash: `/`, `/tarifs`,
     * `/en/features/quests`, `/privacy?lang=en`.
     */
    public static function path(string $key, string $locale): string
    {
        $page = self::page($key);
        $slug = $page['slugs'][$locale] ?? null;

        if ($slug === null) {
            throw new InvalidArgumentException("Site page [{$key}] has no [{$locale}] version.");
        }

        $isDefault = $locale === config('site.default_locale');

        if (($page['locale_strategy'] ?? 'path') === 'query') {
            return '/'.$slug.($isDefault ? '' : '?lang='.$locale);
        }

        $segments = array_filter([$isDefault ? null : $locale, $slug === '' ? null : $slug]);

        return '/'.implode('/', $segments);
    }

    /** Absolute URL on the canonical origin — what SEO tags and the sitemap need. */
    public static function url(string $key, string $locale): string
    {
        $path = self::path($key, $locale);

        return config('site.url').($path === '/' ? '/' : $path);
    }

    /**
     * Every published language of a page, for `hreflang`.
     *
     * @return array<string, string> locale => absolute URL
     */
    public static function alternates(string $key): array
    {
        $urls = [];

        foreach (self::localesFor($key) as $locale) {
            $urls[$locale] = self::url($key, $locale);
        }

        return $urls;
    }

    /**
     * The site language implied by the current URL.
     *
     * For error pages, where no route matched and the controller never ran, the
     * path prefix is the only signal available. Lives here rather than in a view
     * so the layout and the pages that extend it agree — a Blade layout's own
     * `@php` block runs *after* the child's sections are evaluated, so a variable
     * defined there is undefined by the time a `@section` needs it.
     */
    public static function requestLocale(): string
    {
        foreach (config('site.locales') as $locale) {
            if ($locale === config('site.default_locale')) {
                continue;
            }

            if (request()->is($locale, $locale.'/*')) {
                return $locale;
            }
        }

        return config('site.default_locale');
    }

    public static function routeName(string $key, string $locale): string
    {
        return "site.{$locale}.{$key}";
    }

    /**
     * Ancestors of a page, nearest last, excluding the page itself. Feeds both
     * the visible breadcrumb and its BreadcrumbList structured data.
     *
     * @return list<string> page keys
     */
    public static function ancestors(string $key): array
    {
        $trail = [];
        $current = $key;

        while (isset(self::PAGES[$current]['parent'])) {
            $current = self::PAGES[$current]['parent'];
            array_unshift($trail, $current);
        }

        return $trail;
    }
}
