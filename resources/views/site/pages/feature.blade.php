{{--
    Shared by the four pillar pages (quests, people, chapters, constellation).

    One template rather than four near-identical ones: what differs between them is
    copy and a capture, both resolved from the page key, so the pages cannot drift
    apart in layout while their content diverges.
--}}
@extends('site.layout')

@php
    use App\Support\Copy;
    use App\Support\SiteMap;

    $hero = Copy::items("{$copy}.hero");
    $points = Copy::items("{$copy}.points");

    // The other three pillars, for the "keep reading" row at the bottom.
    $siblings = array_values(array_diff(
        ['features.quests', 'features.people', 'features.chapters', 'features.constellation'],
        [$pageKey],
    ));
@endphp

@section('content')

    @include('site.partials.breadcrumbs')

    <section class="night-sky relative overflow-hidden">
        <div aria-hidden="true" class="starfield"></div>

        <div class="container-page relative grid items-center gap-14 py-16 sm:py-20 lg:pb-0 lg:grid-cols-[1.05fr_auto] lg:gap-20">
            <div>
                <p class="mb-4 text-sm font-semibold tracking-[0.12em] text-accent-soft uppercase">
                    {{ $hero['eyebrow'] }}
                </p>
                <h1 class="text-display text-balance">{{ $hero['title'] }}</h1>
                <p class="mt-6 max-w-xl text-lg text-muted sm:text-xl">{{ $hero['lead'] }}</p>
            </div>

            <div class="lg:-mb-36 lg:self-end lg:bleed-fade">
                <x-site.phone :shot="$shot" :alt="$hero['shot_alt']" :width="288" eager />
            </div>
        </div>
    </section>

    <x-site.section tone="ink">
        <div class="mx-auto max-w-3xl divide-y divide-line">
            @foreach ($points as $point)
                <div class="flex gap-6 py-8 first:pt-0">
                    <span aria-hidden="true"
                          class="mt-1 shrink-0 font-semibold text-faint tabular-nums"
                          style="font-family: var(--font-display)">
                        {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}
                    </span>
                    <div>
                        <h2 class="text-heading">{{ $point['title'] }}</h2>
                        <p class="mt-2.5 text-muted">{{ $point['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </x-site.section>

    <x-site.section tone="night" :title="Copy::text('marketing.features.hero.title')">
        <div class="mt-10 grid gap-4 sm:grid-cols-3">
            @foreach ($siblings as $sibling)
                <a href="{{ SiteMap::path($sibling, $locale) }}"
                   class="group rounded-2xl border border-line bg-surface/50 p-6 transition-colors hover:border-line-bright">
                    <h3 class="text-heading transition-colors group-hover:text-accent-soft">
                        {{ Copy::text("marketing.{$sibling}.short") }}
                    </h3>
                    <p class="mt-2 text-sm text-muted">{{ Copy::text("marketing.{$sibling}.hero.lead") }}</p>
                </a>
            @endforeach
        </div>
    </x-site.section>

    <section class="night-sky relative overflow-hidden border-t border-line">
        <div aria-hidden="true" class="starfield"></div>
        <div class="container-page relative py-20 text-center sm:py-24">
            <h2 class="text-title text-balance">{{ Copy::text('marketing.home.cta.title') }}</h2>
            <p class="mx-auto mt-4 max-w-xl text-muted">{{ Copy::text('marketing.home.cta.lead') }}</p>
            <div class="mt-9 flex justify-center">
                <x-site.store-buttons align="center" />
            </div>
        </div>
    </section>

@endsection
