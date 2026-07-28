<?php

namespace App\Http\Controllers;

use App\Support\Copy;
use App\Support\Plus;
use App\Support\SiteMap;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Serves the public legal pages (legal notice, privacy policy, terms of service,
 * support) that the mobile app's About screen links to and that the App Store /
 * Play Store submission requires. These live outside the `/api` surface.
 *
 * They render on the marketing site's layout, so this controller supplies the same
 * payload `SiteController` does — canonical URL, hreflang alternates, breadcrumbs,
 * title and description — on top of the publisher facts the documents themselves
 * quote.
 */
class LegalController extends Controller
{
    public function privacy(Request $request): View
    {
        return view('legal.privacy', $this->viewData($request, 'legal.privacy'));
    }

    public function terms(Request $request): View
    {
        return view('legal.terms', $this->viewData($request, 'legal.terms'));
    }

    public function support(Request $request): View
    {
        return view('legal.support', $this->viewData($request, 'legal.support'));
    }

    /**
     * Publisher identification required in France by LCEN art. 1-1 (recast by
     * loi SREN n° 2024-449 of 21 May 2024) for any professionally operated
     * public online service.
     */
    public function notice(Request $request): View
    {
        return view('legal.notice', $this->viewData($request, 'legal.notice'));
    }

    /**
     * Shared payload for every legal page: the resolved display language, the
     * publisher and hosting facts from `config/legal.php`, and the SEO payload the
     * shared layout needs. No page should hardcode an identification detail — they
     * would drift apart, and a legal notice that contradicts the privacy policy is
     * worse than neither.
     *
     * @return array<string, mixed>
     */
    protected function viewData(Request $request, string $pageKey): array
    {
        $locale = $this->resolveLocale($request);
        app()->setLocale($locale);

        /*
         * The canonical URL follows the shape of the URL that was requested, not the
         * language that content negotiation happened to pick.
         *
         * These paths carry no language prefix, and the app's About screen links to
         * them bare — an English-device user opening /privacy from inside the app gets
         * English via Accept-Language, and that is worth keeping. But if the canonical
         * tracked the negotiated language, the same URL would advertise two different
         * canonicals depending on who asked, which is exactly what a canonical exists
         * to prevent. So: an explicit `?lang=` names its own canonical, and a bare URL
         * is always canonical for the default language — which is also what a crawler
         * gets, since crawlers send no Accept-Language.
         */
        $canonicalLocale = in_array($request->query('lang'), config('site.locales'), true)
            ? $request->query('lang')
            : config('site.default_locale');

        return [
            // `$lang` is what the four content views already read; `$locale` is the
            // name the shared layout and partials use. Same value, both kept so the
            // documents themselves did not have to be edited.
            'lang' => $locale,
            'locale' => $locale,
            'legal' => config('legal'),

            'pageKey' => $pageKey,
            'copy' => "marketing.{$pageKey}",
            'meta' => [
                'title' => Copy::text("marketing.{$pageKey}.meta.title"),
                'description' => Copy::text("marketing.{$pageKey}.meta.description"),
            ],
            'canonical' => SiteMap::url($pageKey, $canonicalLocale),
            'alternates' => SiteMap::alternates($pageKey),
            'breadcrumbs' => [
                [
                    'title' => Copy::text('marketing.nav.home'),
                    'url' => SiteMap::url('home', $locale),
                ],
                [
                    'title' => Copy::text("marketing.{$pageKey}.short"),
                    'url' => SiteMap::url($pageKey, $locale),
                ],
            ],
            'plus' => Plus::forLocale($locale),
        ];
    }

    /**
     * Resolve the display language.
     *
     * An explicit `?lang=` wins. Otherwise the caller's Accept-Language decides — the
     * in-app browser reflects the device locale, so a French phone opening /privacy
     * from the About screen gets French without the app having to build the URL.
     *
     * The two no-match cases are deliberately different, and `getPreferredLanguage()`
     * cannot tell them apart (it returns the first supported language for both):
     *
     *  - No Accept-Language header at all. That is a crawler. It gets the site's
     *    default language, because these bare paths are declared canonical for it —
     *    serving English at a URL whose canonical says French is how a page ends up
     *    indexed in the wrong language.
     *  - A header that names only languages we do not publish (say German). That is a
     *    person, and English serves them better than French. It is also exactly what
     *    the page's own `hreflang="x-default"` already points at.
     */
    protected function resolveLocale(Request $request): string
    {
        $supported = config('site.locales');

        $explicit = $request->query('lang');

        if (is_string($explicit) && in_array($explicit, $supported, true)) {
            return $explicit;
        }

        $offered = array_map(
            fn (string $language): string => substr(str_replace('_', '-', $language), 0, 2),
            $request->getLanguages(),
        );

        foreach ($offered as $language) {
            if (in_array($language, $supported, true)) {
                return $language;
            }
        }

        return $offered === []
            ? config('site.default_locale')
            : config('site.x_default_locale');
    }
}
