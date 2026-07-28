<?php

namespace App\Http\Controllers;

use App\Support\Copy;
use App\Support\Plus;
use App\Support\SiteMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Serves the public marketing site.
 *
 * Every page goes through `show()`. The route supplies the page key and the
 * language as defaults (see `routes/web.php`), and this controller turns that
 * into the SEO payload the layout needs — canonical URL, `hreflang` alternates,
 * breadcrumb trail, title and description — so no Blade template has to know
 * how any of that is derived.
 */
class SiteController extends Controller
{
    public function show(Request $request, string $pageKey, string $locale): View
    {
        app()->setLocale($locale);

        $page = SiteMap::page($pageKey);

        return view(SiteMap::view($pageKey), [
            'pageKey' => $pageKey,
            'locale' => $locale,
            // Lang-file prefix for this page's copy. Page keys are dotted
            // ("features.quests"), which is exactly how the translation arrays
            // nest, so the prefix needs no massaging.
            'copy' => "marketing.{$pageKey}",
            'shot' => $page['shot'] ?? null,
            'meta' => [
                'title' => Copy::text("marketing.{$pageKey}.meta.title"),
                'description' => Copy::text("marketing.{$pageKey}.meta.description"),
            ],
            'canonical' => SiteMap::url($pageKey, $locale),
            'alternates' => SiteMap::alternates($pageKey),
            'breadcrumbs' => $this->breadcrumbs($pageKey, $locale),
            'plus' => Plus::forLocale($locale),
        ]);
    }

    /**
     * Send a visitor straight to the store for the device they're holding.
     *
     * Exists so one short link can be printed, put in a bio, or used in an ad
     * without knowing the platform up front. Anything that isn't a phone — a
     * desktop browser, a crawler — lands on the download page, which shows both
     * stores. Never a hard 301: which store is right depends on the request, so
     * the answer must not be cached by intermediaries.
     */
    public function store(Request $request, string $locale = 'fr'): RedirectResponse
    {
        app()->setLocale($locale);

        $agent = (string) $request->userAgent();
        $ios = preg_match('/iPhone|iPad|iPod/i', $agent) === 1;
        $android = preg_match('/Android/i', $agent) === 1;

        $target = match (true) {
            $ios => config('site.stores.ios.url'),
            $android => config('site.stores.android.url'),
            default => null,
        };

        return redirect()->away($target ?? SiteMap::url('download', $locale), 302);
    }

    /**
     * Visible breadcrumb trail, also reused for the BreadcrumbList JSON-LD.
     *
     * The home page gets no trail — a single-item breadcrumb is noise, and
     * Google ignores it.
     *
     * @return list<array{title: string, url: string}>
     */
    private function breadcrumbs(string $pageKey, string $locale): array
    {
        if ($pageKey === 'home') {
            return [];
        }

        $keys = [...SiteMap::ancestors($pageKey), $pageKey];
        $trail = [[
            'title' => Copy::text('marketing.nav.home'),
            'url' => SiteMap::url('home', $locale),
        ]];

        foreach ($keys as $key) {
            $trail[] = [
                'title' => Copy::text("marketing.{$key}.short"),
                'url' => SiteMap::url($key, $locale),
            ];
        }

        return $trail;
    }
}
