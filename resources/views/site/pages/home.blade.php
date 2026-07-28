@extends('site.layout')

@php
    use App\Support\Copy;
    use App\Support\SiteMap;

    $hero = Copy::items("{$copy}.hero");
    $problem = Copy::items("{$copy}.problem");
    $pillars = Copy::items("{$copy}.pillars");
    $replay = Copy::items("{$copy}.replay");
    $friction = Copy::items("{$copy}.friction");
    $not = Copy::items("{$copy}.not");
    $privacy = Copy::items("{$copy}.privacy");
    $nacre = Copy::items("{$copy}.nacre");
    $pricingBlock = Copy::items("{$copy}.pricing");
    $faq = Copy::items("{$copy}.faq");
    $cta = Copy::items("{$copy}.cta");
@endphp

@section('content')

    {{-- Hero. The screenshot loads eagerly and at high priority because it is the
         largest contentful paint on this page; everything below it is lazy. --}}
    <section class="night-sky overflow-hidden">
        <div aria-hidden="true" class="starfield"></div>

        {{-- The mobile padding and gap are tighter than the section default on
             purpose: on a 390x844 screen every pixel spent above the device is a
             pixel of product the visitor does not see before scrolling. --}}
        <div class="container-page relative grid items-center gap-9 py-12 sm:gap-14 sm:py-24 lg:grid-cols-[1.05fr_auto] lg:gap-20 lg:pb-0">
            <div>
                <p class="mb-5 text-sm font-semibold tracking-[0.12em] text-accent-soft uppercase">
                    {{ $hero['eyebrow'] }}
                </p>

                {{-- `text-hero` rather than `text-display`: only this h1 scales with
                     the viewport, because `text-display` also sets the amounts on
                     /tarifs, where a fluid size would print "44,99 €" at 72 px. --}}
                <h1 class="text-hero text-balance">{{ $hero['title'] }}</h1>

                {{-- The differentiator, at h2 size, directly under the h1. It used to
                     be the 33rd word of the grey paragraph below — the one line a
                     visitor who never scrolls would have missed. --}}
                <p class="mt-5 max-w-xl text-title text-balance">{{ $hero['promise'] }}</p>

                <p class="mt-6 max-w-xl text-lg text-muted sm:text-xl">{{ $hero['lead'] }}</p>

                <div class="mt-8">
                    <x-site.store-buttons :note="false" :soon="false" />
                </div>

                {{-- Reassurance instead of a caveat: the transitional note about the
                     App Store title moved to the closing CTA.

                     Rendered as one wrapped run rather than a bulleted column, because
                     this sits between the buttons and the screenshot on mobile: four
                     stacked lines pushed the device off the fold entirely, and the
                     product being visible is the whole point of putting a device
                     there. No separator glyph on purpose — a `::before` bullet is
                     dimmer than the text it separates at this size, and it lands at
                     the start of the line whenever the run wraps. --}}
                <ul role="list" class="mt-6 flex max-w-2xl flex-wrap gap-x-6 gap-y-1.5 text-sm text-faint">
                    @foreach ($hero['proof'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- On wide screens the device runs off the bottom edge of the hero
                 (clipped by the section's overflow-hidden). A portrait screenshot is
                 far taller than the copy beside it, so containing it whole would
                 leave a band of dead space above the headline. --}}
            <div class="lg:-mb-40 lg:self-end lg:bleed-fade">
                <x-site.phone shot="editor" :alt="$hero['shot_alt']" :width="300" eager />
            </div>
        </div>
    </section>

    {{-- The problem, before the product. A visitor who does not recognise their own
         failed journal here has no reason to read the rest. --}}
    <x-site.section
        :eyebrow="$problem['eyebrow']"
        :title="$problem['title']"
        :lead="$problem['lead']"
        tone="ink">
        <div class="mt-14 grid gap-px overflow-hidden rounded-3xl border border-line bg-line sm:grid-cols-3">
            @foreach ($problem['points'] as $point)
                <div class="bg-ink p-8">
                    <h3 class="text-heading">{{ $point['title'] }}</h3>
                    <p class="mt-3 text-muted">{{ $point['body'] }}</p>
                </div>
            @endforeach
        </div>
    </x-site.section>

    {{-- The four pillars, each with its own capture, sides alternating so the eye
         has to travel and the page does not read as a list. --}}
    <x-site.section
        :eyebrow="$pillars['eyebrow']"
        :title="$pillars['title']"
        :lead="$pillars['lead']"
        tone="night"
        id="fonctionnalites">
        <div class="mt-20 space-y-24 sm:space-y-32">
            @foreach ($pillars['items'] as $index => $item)
                <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
                    <div class="{{ $index % 2 === 1 ? 'lg:order-2' : '' }}">
                        <h3 class="text-title">{{ $item['title'] }}</h3>
                        <p class="mt-5 max-w-lg text-lg text-muted">{{ $item['body'] }}</p>

                        @if (SiteMap::has($item['key']))
                            <a href="{{ SiteMap::path($item['key'], $locale) }}"
                               class="mt-6 inline-flex items-center gap-1.5 font-semibold text-accent-soft hover:underline">
                                {{ Copy::text('marketing.common.learn_more') }}
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        @endif
                    </div>

                    <x-site.phone :shot="$item['shot']" :alt="$item['alt']" :width="288"
                                  class="{{ $index % 2 === 1 ? 'lg:order-1' : '' }}" />
                </div>
            @endforeach
        </div>
    </x-site.section>

    {{-- The differentiator. Given its own section, at full width, because it is the
         one thing competitors do not do. --}}
    <x-site.section tone="ink">
        <div class="mx-auto max-w-3xl text-center">
            <p class="mb-3 text-sm font-semibold tracking-[0.12em] text-accent-soft uppercase">
                {{ $replay['eyebrow'] }}
            </p>
            <h2 class="text-display text-balance">{{ $replay['title'] }}</h2>
            <p class="mt-7 text-lg text-muted sm:text-xl">{{ $replay['lead'] }}</p>
            <p class="mt-5 text-lg text-muted">{{ $replay['body'] }}</p>

            <div class="mt-11 flex justify-center">
                <x-site.store-buttons align="center" :note="false" />
            </div>
        </div>
    </x-site.section>

    <x-site.section
        :eyebrow="$friction['eyebrow']"
        :title="$friction['title']"
        :lead="$friction['lead']"
        tone="surface">
        <ul role="list" class="mt-12 grid gap-x-10 gap-y-5 sm:grid-cols-2">
            @foreach ($friction['points'] as $point)
                <li class="flex gap-3.5">
                    <svg class="mt-1.5 size-4 shrink-0 text-accent-soft" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M2.5 8.5 6 12l7.5-8" stroke="currentColor" stroke-width="1.8"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="text-muted">{{ $point }}</span>
                </li>
            @endforeach
        </ul>
    </x-site.section>

    {{-- Saying what the app refuses to be. Filters out the wrong download, and it
         is where the anti-gamification stance becomes a selling point. --}}
    <x-site.section
        :eyebrow="$not['eyebrow']"
        :title="$not['title']"
        :lead="$not['lead']"
        tone="ink">
        <div class="mt-14 grid gap-5 sm:grid-cols-2">
            @foreach ($not['items'] as $item)
                <div class="rounded-2xl border border-line bg-surface/50 p-7">
                    <h3 class="text-heading">{{ $item['title'] }}</h3>
                    <p class="mt-3 text-muted">{{ $item['body'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-14 flex justify-center">
            <x-site.store-buttons align="center" :note="false" />
        </div>
    </x-site.section>

    <x-site.section tone="night">
        <div class="grid items-center gap-14 lg:grid-cols-[1fr_auto] lg:gap-20">
            <div>
                <p class="mb-3 text-sm font-semibold tracking-[0.12em] text-accent-soft uppercase">
                    {{ $privacy['eyebrow'] }}
                </p>
                <h2 class="text-title text-balance">{{ $privacy['title'] }}</h2>
                <p class="mt-4 text-lg text-muted">{{ $privacy['lead'] }}</p>

                <ul role="list" class="mt-8 space-y-4">
                    @foreach ($privacy['points'] as $point)
                        <li class="flex gap-3.5">
                            <svg class="mt-1.5 size-4 shrink-0 text-accent-soft" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M2.5 8.5 6 12l7.5-8" stroke="currentColor" stroke-width="1.8"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="text-muted">{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ SiteMap::path('privacy', $locale) }}"
                   class="mt-8 inline-flex items-center gap-1.5 font-semibold text-accent-soft hover:underline">
                    {{ $privacy['link'] }}
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

            <x-site.phone shot="lock" :alt="$privacy['shot_alt']" :width="264" />
        </div>
    </x-site.section>

    {{-- The name, and why it is the right one. Doubles as the argument for staying:
         the value of a journal is what accumulates in it. --}}
    <x-site.section tone="ink">
        <div class="mx-auto flex max-w-2xl flex-col items-center text-center">
            <img src="/img/pearl-240.png" alt="" width="112" height="112" class="mb-8 size-28"
                 loading="lazy" decoding="async" aria-hidden="true">
            <h2 class="text-title text-balance">{{ $nacre['title'] }}</h2>
            <p class="mt-5 text-lg text-muted">{{ $nacre['body'] }}</p>
        </div>
    </x-site.section>

    <x-site.section
        :eyebrow="$pricingBlock['eyebrow']"
        :title="$pricingBlock['title']"
        :lead="$pricingBlock['lead']"
        align="center"
        tone="surface">
        {{-- The install button comes first here, and the pricing page second. This
             is where a visitor confirms the app is free; sending them to a page
             about a 6,99 € subscription instead of to the free download put a
             price between the intent and the act. --}}
        <div class="mt-10 flex justify-center">
            <x-site.store-buttons align="center" :note="false" />
        </div>

        <p class="mt-6 text-center text-sm">
            <a href="{{ SiteMap::path('pricing', $locale) }}"
               class="text-faint underline decoration-line-bright underline-offset-4 transition-colors hover:text-accent-soft">
                {{ $pricingBlock['link'] }}
            </a>
        </p>
    </x-site.section>

    {{-- <details>/<summary> rather than a scripted accordion: keyboard-operable and
         announced correctly with no JavaScript, and the answers stay in the HTML
         for crawlers whether open or closed. --}}
    <x-site.section tone="ink">
        <div class="mx-auto max-w-3xl">
            <h2 class="text-title">{{ Copy::text('marketing.faq.hero.title') }}</h2>

            <div class="mt-8 divide-y divide-line border-y border-line">
                @foreach ($faq as $item)
                    <details class="group py-5">
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-6 font-semibold [&::-webkit-details-marker]:hidden">
                            <span>{{ $item['q'] }}</span>
                            <svg class="mt-1.5 size-3.5 shrink-0 text-faint transition-transform group-open:rotate-45"
                                 viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                        </summary>
                        <p class="mt-3 max-w-2xl text-muted">{{ $item['a'] }}</p>
                    </details>
                @endforeach
            </div>

            <a href="{{ SiteMap::path('faq', $locale) }}"
               class="mt-8 inline-flex items-center gap-1.5 font-semibold text-accent-soft hover:underline">
                {{ Copy::text('marketing.common.learn_more') }}
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
    </x-site.section>

    <section class="night-sky relative overflow-hidden">
        <div aria-hidden="true" class="starfield"></div>
        <div class="container-page relative py-24 text-center sm:py-32">
            <h2 class="text-display text-balance">{{ $cta['title'] }}</h2>
            <p class="mx-auto mt-5 max-w-xl text-lg text-muted">{{ $cta['lead'] }}</p>
            <div class="mt-10 flex justify-center">
                <x-site.store-buttons align="center" />
            </div>

            <p class="mx-auto mt-6 max-w-md text-sm text-faint">{{ Copy::text('marketing.common.free_note') }}</p>
        </div>
    </section>

@endsection
