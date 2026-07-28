<?php

namespace Tests\Feature;

use App\Support\Copy;
use App\Support\Plus;
use App\Support\SiteMap;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The public marketing site.
 *
 * These tests guard the properties that are invisible in a browser and therefore
 * rot silently: hreflang reciprocity, canonical stability, sitemap completeness,
 * translation-key parity between the two languages, and the promise that the site
 * sets no cookies. A page rendering is the easy part; a page rendering with a
 * canonical pointing at the wrong host is the failure nobody notices for months.
 */
class SiteTest extends TestCase
{
    /**
     * `Request::create` (and therefore the test client) sets a default
     * `Accept-Language: en-us`, which would make every request negotiate English.
     * Sending nothing is what a crawler does and what the assertions assume.
     */
    private function getPage(string $path): TestResponse
    {
        return $this->withHeaders(['Accept-Language' => ''])->get($path);
    }

    /** @return array<string, array{string, string}> */
    public static function pageProvider(): array
    {
        $cases = [];

        foreach (SiteMap::pages() as $key => $page) {
            foreach (SiteMap::localesFor($key) as $locale) {
                $cases["{$key} [{$locale}]"] = [$key, $locale];
            }
        }

        return $cases;
    }

    #[DataProvider('pageProvider')]
    public function test_every_page_renders(string $key, string $locale): void
    {
        $this->getPage(SiteMap::path($key, $locale))
            ->assertOk()
            ->assertSee('</html>', false);
    }

    #[DataProvider('pageProvider')]
    public function test_every_page_declares_itself_canonical(string $key, string $locale): void
    {
        $expected = SiteMap::url($key, $locale);

        $this->getPage(SiteMap::path($key, $locale))
            ->assertSee('<link rel="canonical" href="'.e($expected).'">', false);
    }

    /**
     * hreflang is only honoured when the annotations are reciprocal: every language
     * version has to list every version, itself included. A one-way link is silently
     * ignored, which is the worst kind of SEO bug.
     */
    #[DataProvider('pageProvider')]
    public function test_every_page_lists_every_language_including_itself(string $key, string $locale): void
    {
        $response = $this->getPage(SiteMap::path($key, $locale));

        foreach (SiteMap::alternates($key) as $altLocale => $altUrl) {
            $response->assertSee(
                '<link rel="alternate" hreflang="'.$altLocale.'" href="'.e($altUrl).'">',
                false,
            );
        }

        $xDefault = SiteMap::alternates($key)[config('site.x_default_locale')];
        $response->assertSee('<link rel="alternate" hreflang="x-default" href="'.e($xDefault).'">', false);
    }

    /**
     * The site removes the session middleware from the `web` group, so no page may
     * set a cookie. Beyond the wasted database round trip, a cookie on a purely
     * informational site is what drags a French visitor into consent-banner
     * territory for no benefit.
     */
    #[DataProvider('pageProvider')]
    public function test_no_page_sets_a_cookie(string $key, string $locale): void
    {
        $response = $this->getPage(SiteMap::path($key, $locale));

        $this->assertSame(
            [],
            $response->headers->getCookies(),
            "[{$key}/{$locale}] set a cookie; the marketing site is meant to set none.",
        );
    }

    /**
     * Copy holds `:monthly`-style tokens that `App\Support\Copy` fills from config.
     * Laravel's translator does not substitute into array lines, so a list read
     * without going through `Copy::items()` renders the raw token to visitors — and
     * into the JSON-LD offers.
     */
    #[DataProvider('pageProvider')]
    public function test_no_page_leaks_an_unresolved_token(string $key, string $locale): void
    {
        $body = $this->getPage(SiteMap::path($key, $locale))->getContent();

        foreach (array_keys(Copy::replacements($locale)) as $token) {
            $this->assertStringNotContainsString(
                ':'.$token,
                $body,
                "[{$key}/{$locale}] rendered the literal token :{$token}.",
            );
        }

        $this->assertStringNotContainsString('marketing.', $body,
            "[{$key}/{$locale}] rendered a translation key, so a lang entry is missing.");
    }

    /**
     * Titles and descriptions are the whole of a search result. Over the limit they
     * are truncated mid-word; empty, Google writes its own.
     */
    #[DataProvider('pageProvider')]
    public function test_title_and_description_are_usable_lengths(string $key, string $locale): void
    {
        app()->setLocale($locale);

        $title = Copy::text("marketing.{$key}.meta.title", $locale);
        $description = Copy::text("marketing.{$key}.meta.description", $locale);

        $this->assertNotSame('', trim($title));
        $this->assertLessThanOrEqual(65, mb_strlen($title), "[{$key}/{$locale}] title is too long: {$title}");

        $this->assertNotSame('', trim($description));
        $this->assertGreaterThanOrEqual(70, mb_strlen($description), "[{$key}/{$locale}] description is too short.");
        // 160 is roughly where Google truncates a description mid-word.
        $this->assertLessThanOrEqual(160, mb_strlen($description), "[{$key}/{$locale}] description is too long: ".mb_strlen($description));
    }

    public function test_french_and_english_copy_have_the_same_shape(): void
    {
        $flatten = function (array $array, string $prefix = '') use (&$flatten): array {
            $keys = [];

            foreach ($array as $key => $value) {
                $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
                $keys = is_array($value)
                    ? [...$keys, ...$flatten($value, $path)]
                    : [...$keys, $path];
            }

            return $keys;
        };

        $fr = $flatten(require lang_path('fr/marketing.php'));
        $en = $flatten(require lang_path('en/marketing.php'));

        $this->assertSame([], array_values(array_diff($fr, $en)), 'Keys present in French but missing in English.');
        $this->assertSame([], array_values(array_diff($en, $fr)), 'Keys present in English but missing in French.');
    }

    public function test_sitemap_lists_every_page_in_every_language_with_its_alternates(): void
    {
        $body = $this->getPage('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $xml = simplexml_load_string($body);
        $this->assertNotFalse($xml, 'sitemap.xml is not well-formed XML.');

        $locations = [];
        foreach ($xml->url as $url) {
            $locations[] = (string) $url->loc;
        }

        foreach (SiteMap::pages() as $key => $page) {
            foreach (SiteMap::localesFor($key) as $locale) {
                $this->assertContains(SiteMap::url($key, $locale), $locations, "Missing from sitemap: {$key} [{$locale}]");
            }
        }

        $this->assertCount(count($locations), array_unique($locations), 'sitemap.xml lists a URL twice.');
        $this->assertStringContainsString('xmlns:xhtml', $body, 'sitemap.xml is missing the hreflang alternates namespace.');
    }

    public function test_robots_points_at_the_sitemap_and_keeps_the_api_out(): void
    {
        // A file in public/ is served by the web server before the request ever
        // reaches the router, so this assertion is the only thing standing between
        // `SitemapController::robots()` and becoming dead code again. `$this->get()`
        // enters through the router and would keep passing regardless.
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'A static public/robots.txt shadows the robots route.');

        $this->getPage('/robots.txt')
            ->assertOk()
            ->assertSee(config('site.url').'/sitemap.xml')
            ->assertSee('Disallow: /api/');
    }

    public function test_structured_data_is_valid_and_priced_from_config(): void
    {
        $body = $this->getPage('/')->getContent();

        $this->assertMatchesRegularExpression(
            '~<script type="application/ld\+json">(.+?)</script>~s',
            $body,
        );
        preg_match('~<script type="application/ld\+json">(.+?)</script>~s', $body, $matches);

        $data = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('https://schema.org', $data['@context']);

        $types = array_column($data['@graph'], '@type');
        $this->assertContains('MobileApplication', $types);
        $this->assertContains('WebSite', $types);

        $app = $data['@graph'][array_search('MobileApplication', $types, true)];
        $prices = array_column($app['offers'], 'price');

        $this->assertContains(number_format(Plus::monthly(), 2, '.', ''), $prices);
        $this->assertContains(number_format(Plus::annual(), 2, '.', ''), $prices);

        // An app with no ratings must not carry a rating. It is a Google penalty and
        // it would be a lie.
        $this->assertArrayNotHasKey('aggregateRating', $app);
    }

    public function test_pricing_page_states_the_saving_its_own_prices_actually_give(): void
    {
        $body = $this->getPage(SiteMap::path('pricing', 'fr'))->getContent();

        $this->assertStringContainsString(Plus::format(Plus::annual(), 'fr'), $body);
        $this->assertStringContainsString(Plus::format(Plus::monthly(), 'fr'), $body);
        $this->assertStringContainsString((string) Plus::annualSavingPercent(), $body);

        // The stated saving has to be the arithmetic one, not a leftover number.
        $this->assertSame(46, Plus::annualSavingPercent());
    }

    /**
     * The legal paths carry no language prefix and the app's About screen links to
     * them bare, so they negotiate content from Accept-Language. The canonical must
     * not follow that negotiation: one URL cannot advertise two canonicals.
     */
    public function test_legal_canonical_does_not_follow_content_negotiation(): void
    {
        $frenchUrl = SiteMap::url('legal.privacy', 'fr');

        foreach (['', 'fr-FR,fr;q=0.9', 'en-US,en;q=0.9'] as $acceptLanguage) {
            $this->withHeaders(['Accept-Language' => $acceptLanguage])
                ->get('/privacy')
                ->assertOk()
                ->assertSee('<link rel="canonical" href="'.e($frenchUrl).'">', false);
        }

        // An explicit ?lang= names its own canonical.
        $this->withHeaders(['Accept-Language' => 'fr-FR'])
            ->get('/privacy?lang=en')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.e(SiteMap::url('legal.privacy', 'en')).'">', false);
    }

    public function test_legal_pages_still_answer_at_the_urls_filed_with_the_stores(): void
    {
        // These exact paths are in App Store Connect and in the app's About screen.
        foreach (['/privacy', '/terms', '/support', '/legal-notice'] as $path) {
            $this->getPage($path)->assertOk();
            $this->getPage($path.'?lang=en')->assertOk();
        }

        $this->getPage('/mentions-legales')->assertRedirect('/legal-notice');
    }

    public function test_french_prefix_redirects_to_the_root(): void
    {
        $this->getPage('/fr')->assertRedirect('/');
    }

    public function test_unknown_url_renders_an_html_page_rather_than_api_json(): void
    {
        $response = $this->getPage('/this-page-does-not-exist');

        $response->assertNotFound();
        $this->assertStringContainsString('<!DOCTYPE html>', $response->getContent());
        $response->assertSee('noindex', false);
    }

    public function test_api_errors_are_still_json(): void
    {
        // The web-facing 404 view must not have changed the API's error envelope.
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertJson(['error' => 'not_found']);
    }

    public function test_store_link_sends_each_device_to_its_own_store(): void
    {
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15';

        $this->withHeaders(['User-Agent' => $iphone])
            ->get('/app')
            ->assertRedirect(config('site.stores.ios.url'));

        // With no Play listing configured, a desktop visitor lands on the page that
        // shows both platforms rather than a dead link.
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'])
            ->get('/app')
            ->assertRedirect(SiteMap::url('download', 'fr'));
    }

    /**
     * The legacy hostname must keep serving the API — shipped binaries have that base
     * URL compiled in and cannot be updated — while its marketing pages fold into the
     * canonical origin.
     */
    public function test_legacy_host_redirects_pages_but_never_the_api(): void
    {
        $legacy = config('site.legacy_hosts')[0];

        $this->getPage('http://'.$legacy.'/tarifs')
            ->assertStatus(301)
            ->assertRedirect(config('site.url').'/tarifs');

        $this->getPage('http://'.$legacy.'/privacy')
            ->assertStatus(301)
            ->assertRedirect(config('site.url').'/privacy');

        $this->getJson('http://'.$legacy.'/api/does-not-exist')
            ->assertNotFound()
            ->assertJson(['error' => 'not_found']);
    }
}
