<?php

namespace App\Http\Controllers;

use App\Support\SiteMap;
use Illuminate\Http\Response;

/**
 * `sitemap.xml`, generated from `App\Support\SiteMap`.
 *
 * Generated rather than committed as a static file so a page can never be added
 * to the site and forgotten here. Each URL carries the full set of `xhtml:link`
 * alternates, which is what Google asks for when the same page exists in several
 * languages: every language version lists every version, itself included.
 *
 * No `<lastmod>`. A date that isn't genuinely the last content change is worse
 * than none — crawlers learn to distrust the whole file.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $locales = config('site.locales');
        $xDefault = config('site.x_default_locale');

        $xml = new \XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('    ');
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

        foreach (SiteMap::pages() as $key => $page) {
            $alternates = SiteMap::alternates($key);

            foreach ($locales as $locale) {
                if (! isset($alternates[$locale])) {
                    continue;
                }

                $xml->startElement('url');
                $xml->writeElement('loc', $alternates[$locale]);

                foreach ($alternates as $altLocale => $altUrl) {
                    $this->alternate($xml, $altLocale, $altUrl);
                }

                if (isset($alternates[$xDefault])) {
                    $this->alternate($xml, 'x-default', $alternates[$xDefault]);
                }

                $xml->writeElement('changefreq', $page['changefreq'] ?? 'monthly');
                $xml->writeElement('priority', $page['priority'] ?? '0.5');
                $xml->endElement();
            }
        }

        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * `robots.txt`.
     *
     * Served from a route rather than `public/robots.txt` so the `Sitemap:` line
     * points at the configured canonical origin instead of a hardcoded host.
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            '# The JSON API is not content — keep it out of the index.',
            'Disallow: /api/',
            'Disallow: /up',
            '',
            'Sitemap: '.config('site.url').'/sitemap.xml',
            '',
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    private function alternate(\XMLWriter $xml, string $hreflang, string $href): void
    {
        $xml->startElement('xhtml:link');
        $xml->writeAttribute('rel', 'alternate');
        $xml->writeAttribute('hreflang', $hreflang);
        $xml->writeAttribute('href', $href);
        $xml->endElement();
    }
}
