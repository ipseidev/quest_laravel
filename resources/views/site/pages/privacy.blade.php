{{--
    The privacy page.

    Structured as two halves — what is true, then what we do not claim — because the
    second half is the point. Any journal app can list reassuring bullets; stating
    plainly that this is not end-to-end encryption, and why, is what makes the first
    list worth believing.
--}}
@extends('site.layout')

@php
    use App\Support\Copy;
    use App\Support\SiteMap;

    $hero = Copy::items("{$copy}.hero");
    $promises = Copy::items("{$copy}.promises");
    $honest = Copy::items("{$copy}.honest");
    $legalLinks = Copy::items("{$copy}.legal_links");

    $legalKeys = ['legal.privacy', 'legal.terms', 'legal.support', 'legal.notice'];
@endphp

@section('content')

    @include('site.partials.breadcrumbs')

    <section class="night-sky relative overflow-hidden">
        <div aria-hidden="true" class="starfield"></div>

        <div class="container-page relative grid items-center gap-14 py-16 sm:py-20 lg:pb-0 lg:grid-cols-[1.1fr_auto] lg:gap-20">
            <div>
                <p class="mb-4 text-sm font-semibold tracking-[0.12em] text-accent-soft uppercase">
                    {{ $hero['eyebrow'] }}
                </p>
                <h1 class="text-display text-balance">{{ $hero['title'] }}</h1>
                <p class="mt-6 max-w-xl text-lg text-muted sm:text-xl">{{ $hero['lead'] }}</p>
            </div>

            <div class="lg:-mb-36 lg:self-end lg:bleed-fade">
                <x-site.phone shot="lock" :alt="$hero['shot_alt']" :width="264" eager />
            </div>
        </div>
    </section>

    <x-site.section tone="ink" :title="$promises['title']">
        <div class="mt-12 grid gap-x-10 gap-y-9 sm:grid-cols-2">
            @foreach ($promises['items'] as $item)
                <div>
                    <h3 class="flex items-start gap-2.5 text-heading">
                        <svg class="mt-1.5 size-4 shrink-0 text-accent-soft" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M2.5 8.5 6 12l7.5-8" stroke="currentColor" stroke-width="1.8"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>{{ $item['title'] }}</span>
                    </h3>
                    <p class="mt-2.5 text-muted">{{ $item['body'] }}</p>
                </div>
            @endforeach
        </div>
    </x-site.section>

    {{-- Deliberately the most prominent block on the page. --}}
    <x-site.section tone="night">
        <div class="mx-auto max-w-3xl">
            <h2 class="text-title text-balance">{{ $honest['title'] }}</h2>
            <p class="mt-4 text-lg text-muted">{{ $honest['lead'] }}</p>

            <div class="mt-10 space-y-5">
                @foreach ($honest['items'] as $item)
                    <div class="rounded-2xl border border-line-bright bg-ink/60 p-7">
                        <h3 class="text-heading">{{ $item['title'] }}</h3>
                        <p class="mt-3 text-muted">{{ $item['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </x-site.section>

    <x-site.section tone="ink" :title="$legalLinks['title']" :lead="$legalLinks['lead']">
        <ul role="list" class="mt-9 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($legalKeys as $key)
                <li>
                    <a href="{{ SiteMap::path($key, $locale) }}"
                       class="block rounded-2xl border border-line bg-surface/40 px-6 py-5 font-semibold transition-colors hover:border-line-bright hover:text-accent-soft">
                        {{ Copy::text("marketing.{$key}.short") }}
                    </a>
                </li>
            @endforeach
        </ul>
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
