{{--
    A page section with an optional heading block.

    `eyebrow` is rendered as plain text, not a heading, so the document outline
    stays h1 -> h2 -> h3 without decorative labels inserting themselves into it.
--}}
@props([
    'eyebrow' => null,
    'title' => null,
    'lead' => null,
    'align' => 'start',
    'tone' => 'ink',
    'id' => null,
])

@php
    $tones = [
        'ink' => 'bg-ink',
        'night' => 'bg-night',
        'surface' => 'bg-surface/40',
    ];
    $centered = $align === 'center';
@endphp

<section @if ($id) id="{{ $id }}" @endif
         {{ $attributes->merge(['class' => 'py-20 sm:py-28 '.($tones[$tone] ?? $tones['ink'])]) }}>
    <div class="container-page">
        @if ($eyebrow || $title || $lead)
            <div class="{{ $centered ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl' }}">
                @if ($eyebrow)
                    <p class="mb-3 text-sm font-semibold tracking-[0.12em] text-accent-soft uppercase">
                        {{ $eyebrow }}
                    </p>
                @endif

                @if ($title)
                    <h2 class="text-title text-balance">{{ $title }}</h2>
                @endif

                @if ($lead)
                    <p class="mt-4 text-lg text-muted">{{ $lead }}</p>
                @endif
            </div>
        @endif

        {{ $slot }}
    </div>
</section>
