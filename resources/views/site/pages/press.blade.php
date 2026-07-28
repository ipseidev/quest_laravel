{{--
    Press kit.

    The boilerplate paragraphs are in <textarea readonly> rather than styled <p>:
    a journalist's first action is to select and copy, and a textarea gives that for
    free — no clipboard script, no "copied!" toast, and select-all works with the
    keyboard alone.
--}}
@extends('site.layout')

@php
    use App\Support\Copy;

    $hero = Copy::items("{$copy}.hero");
    $boilerplate = Copy::items("{$copy}.boilerplate");
    $facts = Copy::items("{$copy}.facts");
    $assets = Copy::items("{$copy}.assets");
    $contact = Copy::items("{$copy}.contact");

    $email = config('legal.contact_email');

    $shots = ['editor', 'quests', 'person', 'constellation', 'pages', 'chapters', 'themes', 'lock'];
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

    <x-site.section tone="ink" :title="$boilerplate['title']">
        <div class="mt-10 space-y-8">
            @foreach ([['short_label', 'short', 4], ['long_label', 'long', 10]] as [$labelKey, $textKey, $rows])
                <div>
                    <label for="press-{{ $textKey }}"
                           class="mb-2 block text-sm font-semibold tracking-[0.1em] text-faint uppercase">
                        {{ $boilerplate[$labelKey] }}
                    </label>
                    <textarea id="press-{{ $textKey }}"
                              readonly
                              rows="{{ $rows }}"
                              class="w-full resize-y rounded-2xl border border-line bg-surface/60 p-5 text-muted"
                    >{{ $boilerplate[$textKey] }}</textarea>
                </div>
            @endforeach
        </div>
    </x-site.section>

    <x-site.section tone="night" :title="$facts['title']">
        <div class="mt-8 max-w-2xl overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <tbody>
                    @foreach ($facts['rows'] as $row)
                        <tr class="border-b border-line">
                            <th scope="row" class="py-3.5 pr-6 align-top font-semibold whitespace-nowrap">
                                {{ $row['label'] }}
                            </th>
                            <td class="py-3.5 align-top text-muted">{{ $row['value'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-site.section>

    <x-site.section tone="ink" :title="$assets['title']" :lead="$assets['lead']">
        <div class="mt-10 grid gap-5 sm:grid-cols-2">
            <div class="flex items-center gap-6 rounded-2xl border border-line bg-surface/40 p-6">
                <img src="/img/pearl-240.png" alt="" width="72" height="72" class="size-18" aria-hidden="true">
                <div>
                    <h3 class="font-semibold">{{ $assets['icon'] }}</h3>
                    <p class="mt-1 text-sm text-faint">{{ $assets['icon_note'] }}</p>
                    <a href="/img/nacre-icon-1024.png" download
                       class="mt-2 inline-block text-sm font-semibold text-accent-soft hover:underline">
                        {{ $assets['download'] }}
                    </a>
                </div>
            </div>

            <div class="flex items-center gap-6 rounded-2xl border border-line bg-surface/40 p-6">
                <img src="/img/og-{{ $locale }}.png" alt="" width="120" height="63"
                     class="w-30 rounded-lg border border-line" aria-hidden="true">
                <div>
                    <h3 class="font-semibold">{{ $assets['og'] }}</h3>
                    <p class="mt-1 text-sm text-faint">{{ $assets['og_note'] }}</p>
                    <a href="/img/og-{{ $locale }}.png" download
                       class="mt-2 inline-block text-sm font-semibold text-accent-soft hover:underline">
                        {{ $assets['download'] }}
                    </a>
                </div>
            </div>
        </div>

        <h3 class="mt-14 font-semibold">{{ $assets['screens'] }}</h3>
        <p class="mt-1 text-sm text-faint">{{ $assets['screens_note'] }}</p>

        <ul role="list" class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-8">
            @foreach ($shots as $shotName)
                <li>
                    <a href="/img/app/en/{{ $shotName }}-960.webp" download class="block">
                        <img src="/img/app/en/{{ $shotName }}-320.webp"
                             alt="{{ $shotName }}"
                             width="1320" height="2868" loading="lazy" decoding="async"
                             class="w-full rounded-xl border border-line">
                    </a>
                </li>
            @endforeach
        </ul>
    </x-site.section>

    <x-site.section tone="night">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-title">{{ $contact['title'] }}</h2>
            <p class="mt-4 text-muted">{{ $contact['body'] }}</p>
            <p class="mt-7">
                <x-site.button :href="'mailto:'.$email">{{ $email }}</x-site.button>
            </p>
        </div>
    </x-site.section>

@endsection
