{{--
    Structured data, emitted as a single @graph so the entities can reference each
    other by @id instead of being repeated.

    Two things are deliberately absent:

    - aggregateRating. The listing has no ratings yet. Inventing one is both a
      Google penalty and a lie.
    - any operatingSystem entry for a store that isn't public. The value is
      derived from which store URLs are actually configured, so the graph cannot
      claim an Android release before there is one.
--}}
@php
    $site = config('site.url');
    $publisher = config('legal.publisher');
    $stores = config('site.stores');
    $currency = config('site.plus.currency');

    $platforms = array_values(array_filter([
        $stores['ios']['url'] ? $stores['ios']['min_os'] : null,
        $stores['android']['url'] ? $stores['android']['min_os'] : null,
    ]));

    $screenshots = array_map(
        fn (string $slug): string => $site.'/img/app/en/'.$slug.'-960.webp',
        ['editor', 'quests', 'person', 'constellation', 'chapters'],
    );

    $application = array_filter([
        '@type' => 'MobileApplication',
        '@id' => $site.'/#app',
        'name' => 'Nacre',
        // Kept while the store listing still shows the pre-rename title, so a
        // search for the old name still resolves to this entity.
        'alternateName' => $stores['previous_name'],
        'applicationCategory' => 'LifestyleApplication',
        'applicationSubCategory' => \App\Support\Copy::text('marketing.common.category'),
        'operatingSystem' => $platforms === [] ? null : implode(', ', $platforms),
        'url' => \App\Support\SiteMap::url('home', $locale),
        'installUrl' => $stores['ios']['url'] ?: null,
        'inLanguage' => config('site.locales'),
        'isAccessibleForFree' => true,
        'description' => \App\Support\Copy::text('marketing.common.app_description'),
        'image' => $site.'/img/og-'.$locale.'.png',
        'screenshot' => $screenshots,
        'author' => ['@id' => $site.'/#publisher'],
        'publisher' => ['@id' => $site.'/#publisher'],
        'offers' => [
            [
                '@type' => 'Offer',
                'name' => \App\Support\Copy::text('marketing.pricing.free.name'),
                'price' => '0',
                'priceCurrency' => $currency,
                'availability' => 'https://schema.org/InStock',
            ],
            [
                '@type' => 'Offer',
                'name' => \App\Support\Copy::text('marketing.pricing.plus.name').' — '.\App\Support\Copy::text('marketing.pricing.plus.monthly_label'),
                'price' => $plus['monthly_raw'],
                'priceCurrency' => $currency,
                'availability' => 'https://schema.org/InStock',
            ],
            [
                '@type' => 'Offer',
                'name' => \App\Support\Copy::text('marketing.pricing.plus.name').' — '.\App\Support\Copy::text('marketing.pricing.plus.annual_label'),
                'price' => $plus['annual_raw'],
                'priceCurrency' => $currency,
                'availability' => 'https://schema.org/InStock',
            ],
        ],
    ], fn ($value): bool => $value !== null);

    $graph = [
        [
            '@type' => 'Person',
            '@id' => $site.'/#publisher',
            'name' => $publisher['name'],
            'url' => \App\Support\SiteMap::url('about', $locale),
        ],
        [
            '@type' => 'WebSite',
            '@id' => $site.'/#website',
            'name' => 'Nacre',
            'url' => $site,
            'inLanguage' => config('site.locales'),
            'publisher' => ['@id' => $site.'/#publisher'],
        ],
        $application,
    ];

    if (! empty($breadcrumbs)) {
        $graph[] = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values(array_map(fn (int $index, array $crumb): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['title'],
                'item' => $crumb['url'],
            ], array_keys($breadcrumbs), $breadcrumbs)),
        ];
    }

    // Any page whose copy declares a `faq` block gets FAQPage markup, which is
    // what earns the expandable answers in search results.
    if (\App\Support\Copy::has("marketing.{$pageKey}.faq")) {
        $graph[] = [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
            ], \App\Support\Copy::items("marketing.{$pageKey}.faq")),
        ];
    }
    // Encoded inside this @php block, not in the echo below, and that placement is
    // load-bearing: Blade extracts @php blocks before it scans for directives, so
    // the literal "@context" key survives. Written in the template body it compiles
    // as Laravel 13's @context directive and the document ends up with PHP source
    // where schema.org's URL belongs.
    //
    // JSON_HEX_TAG so a "</script>" inside any string cannot break out of the tag.
    $jsonLd = json_encode(
        ['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG,
    );
@endphp
<script type="application/ld+json">{!! $jsonLd !!}</script>
