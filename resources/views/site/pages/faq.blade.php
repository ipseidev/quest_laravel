{{--
    FAQ.

    Questions are stored as one flat list carrying a `group` key, then grouped here.
    Flat is what site.partials.jsonld needs for the FAQPage markup, and grouping in
    the view means the two can never fall out of sync — there is one list.

    Every answer is in the HTML whether its <details> is open or closed, so a crawler
    reads all of it. The first group starts open, because a visitor who lands here
    should see an answer without clicking.
--}}
@extends('site.layout')

@php
    use App\Support\Copy;

    $hero = Copy::items("{$copy}.hero");
    $groupLabels = Copy::items("{$copy}.groups");
    $items = Copy::items("{$copy}.faq");

    $grouped = [];
    foreach ($items as $item) {
        $grouped[$item['group']][] = $item;
    }
@endphp

@section('content')

    @include('site.partials.breadcrumbs')

    <section class="night-sky relative overflow-hidden">
        <div aria-hidden="true" class="starfield"></div>
        <div class="container-page relative py-16 sm:py-20">
            <p class="mb-4 text-sm font-semibold tracking-[0.12em] text-accent-soft uppercase">
                {{ $hero['eyebrow'] }}
            </p>
            <h1 class="text-display text-balance">{{ $hero['title'] }}</h1>
            <p class="mt-6 max-w-2xl text-lg text-muted sm:text-xl">{{ $hero['lead'] }}</p>
        </div>
    </section>

    <x-site.section tone="ink">
        <div class="mx-auto max-w-3xl space-y-16">
            @foreach ($grouped as $group => $questions)
                <section>
                    <h2 class="text-sm font-semibold tracking-[0.12em] text-faint uppercase"
                        style="font-family: var(--font-sans)">
                        {{ $groupLabels[$group] ?? $group }}
                    </h2>

                    <div class="mt-5 divide-y divide-line border-y border-line">
                        @foreach ($questions as $item)
                            <details class="group py-5" @if ($loop->parent->first && $loop->first) open @endif>
                                <summary class="flex cursor-pointer list-none items-start justify-between gap-6 font-semibold [&::-webkit-details-marker]:hidden">
                                    <h3 class="font-sans text-base font-semibold">{{ $item['q'] }}</h3>
                                    <svg class="mt-1.5 size-3.5 shrink-0 text-faint transition-transform group-open:rotate-45"
                                         viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                        <path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                    </svg>
                                </summary>
                                <p class="mt-3 max-w-2xl text-muted">{{ $item['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </section>
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
