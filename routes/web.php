<?php

use App\Http\Controllers\LegalController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use App\Support\SiteMap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketing site
|--------------------------------------------------------------------------
|
| One route per (page, language) pair, generated from `App\Support\SiteMap`.
| The page key and the language ride along as route defaults, which is what lets
| a single controller action serve every page while each URL keeps its own name
| for `route()` calls and its own slug per language.
|
| French sits at the root and English under `/en` — the market is France first,
| and the slugs differ per language on purpose (a French visitor searches
| "tarifs", not "pricing").
|
*/

foreach (SiteMap::routedKeys() as $pageKey) {
    foreach (SiteMap::localesFor($pageKey) as $locale) {
        Route::get(SiteMap::path($pageKey, $locale), [SiteController::class, 'show'])
            ->defaults('pageKey', $pageKey)
            ->defaults('locale', $locale)
            ->name(SiteMap::routeName($pageKey, $locale));
    }
}

// One short link that resolves to the right store for the device asking. 302,
// never 301: the answer depends on the request and must not be cached.
Route::get('/app', [SiteController::class, 'store'])->name('site.store');
Route::get('/en/app', [SiteController::class, 'store'])
    ->defaults('locale', 'en')
    ->name('site.en.store');

// French is served from the root, so `/fr` is only ever a guess. Send it home
// rather than 404 on it.
Route::permanentRedirect('/fr', '/');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('site.sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('site.robots');

/*
|--------------------------------------------------------------------------
| Legal pages
|--------------------------------------------------------------------------
|
| These predate the site. The mobile About screen links to /privacy, /terms and
| /legal-notice, and /privacy + /support are what was filed in App Store Connect,
| so the paths are frozen; the language is resolved from `?lang=` or
| Accept-Language rather than from a path prefix. `App\Support\SiteMap` lists them
| too, so they appear in the sitemap and the footer alongside everything else.
|
*/

Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
// Required as the App Store / Play Store "Support URL".
Route::get('/support', [LegalController::class, 'support'])->name('legal.support');
// Publisher identification (LCEN art. 1-1, recast by loi SREN n° 2024-449 of
// 21 May 2024 — the old art. 6-III no longer exists). Canonical URL follows the
// English scheme of its siblings; the French wording is kept as an alias
// because that is what a French user (or a regulator) types.
Route::get('/legal-notice', [LegalController::class, 'notice'])->name('legal.notice');

// The French wording is kept as an alias because it is what a French user (or a
// regulator) types. It redirects rather than rendering a second copy, so the two URLs
// don't compete as duplicate content — carrying `?lang=` across, which a bare
// `permanentRedirect` would drop.
Route::get('/mentions-legales', function (Request $request) {
    $query = $request->getQueryString();

    return redirect('/legal-notice'.($query === null ? '' : '?'.$query), 301);
});
