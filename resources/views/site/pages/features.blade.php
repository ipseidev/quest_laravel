@extends('site.layout')

@php
    use App\Support\Copy;
    use App\Support\SiteMap;

    $hero = Copy::items("{$copy}.hero");
    $basics = Copy::items("{$copy}.basics");

    // The pillar pages, with their capture, read straight from the registry so this
    // hub cannot list a page that does not exist.
    $pillars = ['features.quests', 'features.people', 'features.chapters', 'features.constellation'];
@endphp

@section('content')

    @include('site.partials.breadcrumbs')

    <section class="night-sky relative overflow-hidden">
        <div aria-hidden="true" class="starfield"></div>
        <div class="container-page relative py-16 sm:py-20">
            <p class="mb-4 text-sm font-semibold tracking-[0.12em] text-accent-soft uppercase">
                {{ $hero['eyebrow'] }}
            </p>
            <h1 class="max-w-3xl text-display text-balance">{{ $hero['title'] }}</h1>
            <p class="mt-6 max-w-2xl text-lg text-muted sm:text-xl">{{ $hero['lead'] }}</p>
        </div>
    </section>

    {{-- The four pillars as full cards with their capture: this page's job is to
         route the visitor to the one thing they came looking for. --}}
    <x-site.section tone="ink">
        <div class="grid gap-6 sm:grid-cols-2">
            @foreach ($pillars as $key)
                @php $page = SiteMap::page($key); @endphp
                <a href="{{ SiteMap::path($key, $locale) }}"
                   class="group flex flex-col gap-7 rounded-3xl border border-line bg-surface/40 p-8 transition-colors hover:border-line-bright sm:flex-row sm:items-start">
                    <div class="order-2 sm:order-1">
                        <h2 class="text-heading transition-colors group-hover:text-accent-soft">
                            {{ Copy::text("marketing.{$key}.hero.title") }}
                        </h2>
                        <p class="mt-3 text-muted">{{ Copy::text("marketing.{$key}.hero.lead") }}</p>
                        <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-accent-soft">
                            {{ Copy::text('marketing.common.learn_more') }}
                            <span aria-hidden="true" class="transition-transform group-hover:translate-x-0.5">&rarr;</span>
                        </span>
                    </div>

                    <x-site.phone :shot="$page['shot']"
                                  :alt="Copy::text('marketing.'.$key.'.hero.shot_alt')"
                                  :width="132"
                                  class="order-1 shrink-0 sm:order-2 sm:mx-0" />
                </a>
            @endforeach
        </div>
    </x-site.section>

    <x-site.section tone="night" :title="$basics['title']">
        <div class="mt-12 grid gap-x-10 gap-y-9 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($basics['items'] as $item)
                <div>
                    <h3 class="text-heading">{{ $item['title'] }}</h3>
                    <p class="mt-2.5 text-muted">{{ $item['body'] }}</p>
                </div>
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
