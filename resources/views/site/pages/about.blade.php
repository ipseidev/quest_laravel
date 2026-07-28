@extends('site.layout')

@php
    use App\Support\Copy;

    $hero = Copy::items("{$copy}.hero");
    $story = Copy::items("{$copy}.story");
    $principles = Copy::items("{$copy}.principles");
    $contact = Copy::items("{$copy}.contact");

    $email = config('legal.contact_email');
@endphp

@section('content')

    @include('site.partials.breadcrumbs')

    <section class="night-sky relative overflow-hidden">
        <div aria-hidden="true" class="starfield"></div>
        <div class="container-page relative py-16 sm:py-20">
            <img src="/img/pearl-240.png" alt="" width="88" height="88" class="mb-8 size-22" aria-hidden="true">
            <p class="mb-4 text-sm font-semibold tracking-[0.12em] text-accent-soft uppercase">
                {{ $hero['eyebrow'] }}
            </p>
            <h1 class="max-w-3xl text-display text-balance">{{ $hero['title'] }}</h1>
            <p class="mt-6 max-w-2xl text-lg text-muted sm:text-xl">{{ $hero['lead'] }}</p>
        </div>
    </section>

    {{-- First person, set in the display serif at reading width: this is the one page
         where the voice matters more than the layout. --}}
    <x-site.section tone="ink">
        <div class="mx-auto max-w-2xl">
            <h2 class="text-title">{{ $story['title'] }}</h2>
            <div class="mt-6 space-y-5">
                @foreach ($story['body'] as $paragraph)
                    <p class="text-lg text-muted">{{ $paragraph }}</p>
                @endforeach
            </div>
        </div>
    </x-site.section>

    <x-site.section tone="night" :title="$principles['title']">
        <ol role="list" class="mt-12 grid gap-x-10 gap-y-9 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($principles['items'] as $item)
                <li>
                    <p class="text-sm font-semibold text-faint tabular-nums" aria-hidden="true"
                       style="font-family: var(--font-display)">
                        {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}
                    </p>
                    <h3 class="mt-2 text-heading">{{ $item['title'] }}</h3>
                    <p class="mt-2 text-muted">{{ $item['body'] }}</p>
                </li>
            @endforeach
        </ol>
    </x-site.section>

    <x-site.section tone="ink">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-title">{{ $contact['title'] }}</h2>
            <p class="mt-4 text-muted">{{ $contact['body'] }}</p>
            <p class="mt-7">
                <x-site.button :href="'mailto:'.$email">{{ $email }}</x-site.button>
            </p>
        </div>
    </x-site.section>

@endsection
