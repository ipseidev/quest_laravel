@extends('site.layout')

@php
    use App\Support\Copy;

    $hero = Copy::items("{$copy}.hero");
    $ios = Copy::items("{$copy}.ios");
    $android = Copy::items("{$copy}.android");
    $next = Copy::items("{$copy}.next");

    $stores = config('site.stores');
@endphp

@section('content')

    @include('site.partials.breadcrumbs')

    <section class="night-sky relative overflow-hidden">
        <div aria-hidden="true" class="starfield"></div>
        <div class="container-page relative py-16 text-center sm:py-20">
            <h1 class="mx-auto max-w-2xl text-display text-balance">{{ $hero['title'] }}</h1>
            <p class="mx-auto mt-6 max-w-xl text-lg text-muted">{{ $hero['lead'] }}</p>
        </div>
    </section>

    <x-site.section tone="ink" class="pt-4!">
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="rounded-3xl border border-line bg-surface/40 p-8">
                <h2 class="text-heading">{{ $ios['title'] }}</h2>
                <p class="mt-3 text-muted">{{ $ios['body'] }}</p>

                <div class="mt-7">
                    @if ($stores['ios']['url'])
                        <x-site.button :href="$stores['ios']['url']" external>
                            {{ Copy::text('marketing.common.download_ios') }}
                        </x-site.button>
                    @else
                        <x-site.button variant="muted">
                            {{ Copy::text('marketing.common.soon_ios') }}
                        </x-site.button>
                    @endif
                </div>

                @if ($stores['ios']['url'] && $stores['previous_name'])
                    <p class="mt-4 text-sm text-faint">
                        {{ __('marketing.common.previous_name_note', ['store_name' => $stores['previous_name']]) }}
                    </p>
                @endif
            </div>

            <div class="rounded-3xl border border-line bg-surface/40 p-8">
                <h2 class="text-heading">{{ $android['title'] }}</h2>
                <p class="mt-3 text-muted">{{ $android['body'] }}</p>

                <div class="mt-7">
                    @if ($stores['android']['url'])
                        <x-site.button :href="$stores['android']['url']" variant="secondary" external>
                            {{ Copy::text('marketing.common.download_android') }}
                        </x-site.button>
                    @else
                        <x-site.button variant="muted">
                            {{ Copy::text('marketing.common.soon_android') }}
                        </x-site.button>
                    @endif
                </div>
            </div>
        </div>

        <p class="mt-8 text-sm text-faint">{{ Copy::text('marketing.common.free_note') }}</p>
    </x-site.section>

    <x-site.section tone="night" :title="$next['title']">
        <ol role="list" class="mt-12 grid gap-10 sm:grid-cols-3">
            @foreach ($next['steps'] as $step)
                <li>
                    <p class="text-sm font-semibold text-accent-soft tabular-nums" aria-hidden="true"
                       style="font-family: var(--font-display)">
                        {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}
                    </p>
                    <h3 class="mt-2 text-heading">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-muted">{{ $step['body'] }}</p>
                </li>
            @endforeach
        </ol>
    </x-site.section>

@endsection
