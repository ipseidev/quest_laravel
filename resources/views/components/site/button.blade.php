{{--
    The site's only button.

    `primary` is paper-on-ink rather than the app's violet, because white text on
    --color-accent is 3.3:1 — below WCAG AA. Paper on ink is 19:1, it reads as
    unmistakably the main action against a dark page, and it leaves the violet
    free to mean "accent" everywhere else instead of competing with itself.

    `muted` renders a non-interactive chip. It exists for a store that isn't
    public yet: showing the platform greyed out communicates "coming" far better
    than hiding it, and it is not a link, so nobody taps a dead end.
--}}
@props([
    'href' => null,
    'variant' => 'primary',
    'external' => false,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-[0.9375rem] font-semibold transition-colors';

    $variants = [
        'primary' => 'bg-paper text-ink hover:bg-white',
        'secondary' => 'border border-line-bright text-paper hover:border-accent-soft hover:text-accent-soft',
        'muted' => 'border border-line text-faint cursor-default select-none',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($variant === 'muted' || $href === null)
    <span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
@else
    <a href="{{ $href }}"
       @if ($external) rel="noopener" @endif
       {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@endif
