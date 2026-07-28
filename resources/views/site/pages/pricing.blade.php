{{--
    Pricing.

    No monthly/yearly toggle: both prices are on the page at once. A toggle hides
    half the answer behind an interaction, keeps one price out of the HTML a crawler
    reads, and needs JavaScript to do it. Showing both, with the yearly plan's
    per-month equivalent and its saving computed from the two amounts, answers the
    question in one look.

    Every figure comes from `config/site.php` through `App\Support\Plus`, so the
    discount stated here is arithmetically the discount the store charges.
--}}
@extends('site.layout')

@php
    use App\Support\Copy;
    use App\Support\SiteMap;

    $hero = Copy::items("{$copy}.hero");
    $free = Copy::items("{$copy}.free");
    $plusPlan = Copy::items("{$copy}.plus");
    $why = Copy::items("{$copy}.why");
    $faq = Copy::items("{$copy}.faq");
@endphp

@section('content')

    @include('site.partials.breadcrumbs')

    <section class="night-sky relative overflow-hidden">
        <div aria-hidden="true" class="starfield"></div>
        <div class="container-page relative py-16 text-center sm:py-20">
            <p class="mb-4 text-sm font-semibold tracking-[0.12em] text-accent-soft uppercase">
                {{ $hero['eyebrow'] }}
            </p>
            <h1 class="mx-auto max-w-3xl text-display text-balance">{{ $hero['title'] }}</h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-muted">{{ $hero['lead'] }}</p>
        </div>
    </section>

    <x-site.section tone="ink" class="pt-4!">
        <div class="grid items-start gap-6 lg:grid-cols-2">

            {{-- Free --}}
            <div class="flex h-full flex-col rounded-3xl border border-line bg-surface/40 p-8 sm:p-10">
                <h2 class="text-heading">{{ $free['name'] }}</h2>
                <p class="mt-1 text-muted">{{ $free['summary'] }}</p>

                <p class="mt-8 text-display">{{ $free['price'] }}</p>
                <p class="mt-2 text-sm text-faint">{{ $free['price_note'] }}</p>

                <ul role="list" class="mt-9 space-y-3.5">
                    @foreach ($free['items'] as $item)
                        <li class="flex gap-3.5">
                            <svg class="mt-1.5 size-4 shrink-0 text-faint" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M2.5 8.5 6 12l7.5-8" stroke="currentColor" stroke-width="1.8"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="text-muted">{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-10">
                    <x-site.button :href="SiteMap::path('download', $locale)" variant="secondary">
                        {{ $free['cta'] }}
                    </x-site.button>
                </div>
            </div>

            {{-- Plus --}}
            <div class="relative flex h-full flex-col rounded-3xl border border-accent/50 bg-surface p-8 sm:p-10">
                <div aria-hidden="true"
                     class="pointer-events-none absolute -inset-px -z-10 rounded-3xl bg-accent/20 blur-2xl"></div>

                <h2 class="text-heading">{{ $plusPlan['name'] }}</h2>
                <p class="mt-1 text-muted">{{ $plusPlan['summary'] }}</p>

                <div class="mt-8 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="text-display">{{ $plus['annual'] }}</span>
                    <span class="text-muted">{{ $plusPlan['annual_label'] }}</span>
                </div>
                <p class="mt-2 text-sm font-semibold text-accent-soft">{{ $plusPlan['annual_badge'] }}</p>
                <p class="mt-1 text-sm text-faint">
                    {{ $plus['monthly'] }} {{ $plusPlan['monthly_label'] }}
                </p>

                <ul role="list" class="mt-9 space-y-3.5">
                    @foreach ($plusPlan['items'] as $item)
                        <li class="flex gap-3.5">
                            <svg class="mt-1.5 size-4 shrink-0 text-accent-soft" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M2.5 8.5 6 12l7.5-8" stroke="currentColor" stroke-width="1.8"
                                      stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="text-muted">{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-10">
                    {{-- Not a link: the subscription is only purchasable inside the app,
                         through the store's own billing. Sending someone to a web
                         checkout that does not exist would be worse than saying so. --}}
                    <x-site.button variant="muted">{{ $plusPlan['cta'] }}</x-site.button>
                    <p class="mt-4 max-w-sm text-sm text-faint">{{ $plusPlan['cta_note'] }}</p>
                </div>
            </div>
        </div>
    </x-site.section>

    <x-site.section tone="night">
        <div class="mx-auto max-w-2xl">
            <h2 class="text-title text-balance">{{ $why['title'] }}</h2>
            <p class="mt-5 text-lg text-muted">{{ $why['body'] }}</p>
        </div>
    </x-site.section>

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

@endsection
