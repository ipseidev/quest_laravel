<?php

namespace Tests\Feature;

use App\Support\SiteMap;
use Tests\TestCase;

/**
 * The four public legal pages are what App Review reads and what a French
 * regulator would read. They are also the easiest thing in the codebase to let
 * rot: they have no callers, no types, and nothing else breaks when they go
 * stale.
 *
 * These tests pin the two properties that matter — every page renders in both
 * languages, and no page ships an unfilled placeholder — plus the publisher
 * identification that LCEN art. 6-III requires.
 */
class LegalPagesTest extends TestCase
{
    /** @var list<string> */
    protected array $paths = ['/privacy', '/terms', '/support', '/legal-notice'];

    public function test_every_legal_page_renders_in_both_languages(): void
    {
        foreach ($this->paths as $path) {
            foreach (['fr', 'en'] as $lang) {
                $this->get("{$path}?lang={$lang}")
                    ->assertOk()
                    ->assertSee('Nacre', escape: false);
            }
        }
    }

    /**
     * `/mentions-legales` now redirects to `/legal-notice` instead of rendering a
     * second copy of it: with the pages indexable, two URLs serving identical content
     * compete with each other. The alias stays — it is what a French user types — and
     * it carries `?lang=` across so the redirect doesn't silently change language.
     */
    public function test_the_french_legal_notice_url_redirects_to_the_canonical_one(): void
    {
        $this->get('/mentions-legales?lang=fr')
            ->assertStatus(301)
            ->assertRedirect('/legal-notice?lang=fr');

        $this->get('/mentions-legales')
            ->assertStatus(301)
            ->assertRedirect('/legal-notice');

        $this->followingRedirects()
            ->get('/mentions-legales?lang=fr')
            ->assertOk()
            ->assertSee('Mentions légales', escape: false);
    }

    /**
     * Three distinct cases, and the difference between the last two is deliberate.
     *
     * A matched language is served. A request with *no* Accept-Language is a crawler,
     * and it gets French: these bare paths are declared canonical for French, so
     * serving English here is how a page gets indexed in the wrong language. A request
     * naming only languages we don't publish is a person, and English serves them
     * better — which is also what the page's own `hreflang="x-default"` points at.
     */
    public function test_language_follows_accept_language_with_a_crawler_specific_fallback(): void
    {
        $this->get('/privacy', ['Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->assertOk()
            ->assertSee('Politique de confidentialité', escape: false);

        $this->get('/privacy', ['Accept-Language' => 'en-GB,en;q=0.9'])
            ->assertOk()
            ->assertSee('Privacy Policy', escape: false);

        // Unsupported language named: fall back to English, as x-default advertises.
        $this->get('/privacy', ['Accept-Language' => 'de-DE,de;q=0.9'])
            ->assertOk()
            ->assertSee('Privacy Policy', escape: false);

        // No header at all: French, matching the canonical this URL declares.
        $this->withHeaders(['Accept-Language' => ''])
            ->get('/privacy')
            ->assertOk()
            ->assertSee('Politique de confidentialité', escape: false);
    }

    /**
     * The pages shipped for months with bracketed drafting notes
     * (`[ENTITÉ LÉGALE]`, `[DATE À DÉFINIR]`, `[PROVIDER]`). Publishing one of
     * those is worse than publishing nothing, so fail the suite rather than the
     * submission.
     */
    public function test_no_page_contains_an_unfilled_placeholder(): void
    {
        foreach ($this->paths as $path) {
            foreach (['fr', 'en'] as $lang) {
                $body = $this->get("{$path}?lang={$lang}")->assertOk()->getContent();

                $this->assertIsString($body);
                $this->assertDoesNotMatchRegularExpression(
                    '/\[[A-ZÀ-Ÿ][^\]]{2,}\]/u',
                    $body,
                    "{$path} ({$lang}) still contains a bracketed placeholder"
                );
                $this->assertStringNotContainsStringIgnoringCase('TODO', $body);
            }
        }
    }

    public function test_legal_notice_identifies_the_publisher(): void
    {
        $publisher = config('legal.publisher');

        $this->get('/legal-notice?lang=fr')
            ->assertOk()
            ->assertSee($publisher['name'])
            ->assertSee($publisher['siren'])
            ->assertSee($publisher['siret'])
            ->assertSee($publisher['vat'])
            ->assertSee(config('legal.contact_email'))
            ->assertSee(config('legal.hosting.region'));
    }

    /**
     * The publisher's postal address and phone are withheld today and offered
     * on request instead. Both are LCEN art. 1-1, I, 1° elements, so the page
     * must start publishing them the moment `config/legal.php` holds a value —
     * that is the whole point of the config indirection.
     */
    public function test_legal_notice_publishes_the_address_and_phone_once_configured(): void
    {
        $this->get('/legal-notice?lang=fr')
            ->assertOk()
            ->assertSee('communiqués sur demande', escape: false);

        config([
            'legal.publisher.address' => '1 rue de la Paix, 75002 Paris',
            'legal.publisher.phone' => '+33 1 23 45 67 89',
        ]);

        $this->get('/legal-notice?lang=fr')
            ->assertOk()
            ->assertSee('1 rue de la Paix, 75002 Paris')
            ->assertSee('+33 1 23 45 67 89')
            ->assertDontSee('communiqués sur demande', escape: false);
    }

    public function test_privacy_policy_discloses_the_ai_processor_and_the_data_location(): void
    {
        $this->get('/privacy?lang=fr')
            ->assertOk()
            ->assertSee('Anthropic')
            ->assertSee('RevenueCat')
            ->assertSee('Cloudflare')
            ->assertSee(config('legal.hosting.region_label'));
    }

    /**
     * The legal pages now share the marketing site's footer, which links the legal
     * notice at its canonical path — `/legal-notice`, with no `?lang=fr`, because
     * French is the default language and the redundant parameter would make a second
     * URL for the same page. Asserted through `SiteMap` so the expectation follows the
     * registry instead of pinning a hand-written string.
     */
    public function test_every_page_links_to_the_legal_notice(): void
    {
        $expected = SiteMap::path('legal.notice', 'fr');

        foreach ($this->paths as $path) {
            $this->get("{$path}?lang=fr")
                ->assertOk()
                ->assertSee('href="'.$expected.'"', escape: false);
        }
    }
}
