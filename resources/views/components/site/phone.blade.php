{{--
    A screenshot in a device frame.

    Each capture was pre-encoded to AVIF and WebP at three widths (see the asset
    build), so this serves whichever the browser prefers at the size it will
    actually paint. `width`/`height` carry the true pixel dimensions, which gives
    the browser the aspect ratio before the bytes land — no layout shift.

    Captures live under /img/app/<language>/. Only English exists today; drop a
    French set into /img/app/fr/ using the same filenames and this picks it up on
    its own, no template change.
--}}
@props([
    'shot',
    'alt',
    'width' => 300,
    'eager' => false,
])

@php
    $locale = app()->getLocale();
    $dir = file_exists(public_path("img/app/{$locale}/{$shot}-640.webp")) ? $locale : 'en';
    $path = "/img/app/{$dir}/{$shot}";

    $srcset = fn (string $ext): string => implode(', ', array_map(
        fn (int $w): string => "{$path}-{$w}.{$ext} {$w}w",
        [320, 640, 960],
    ));
@endphp

<div {{ $attributes->merge(['class' => 'relative mx-auto w-full']) }} style="max-width: {{ $width }}px">
    {{-- A violet bloom behind the device, so a dark screenshot doesn't read as a
         hole punched in the page. --}}
    <div class="pointer-events-none absolute -inset-6 -z-10 rounded-full bg-accent/20 blur-3xl" aria-hidden="true"></div>

    <div class="overflow-hidden rounded-[2.25rem] border border-line-bright bg-surface p-1.5 shadow-2xl shadow-black/60">
        <picture>
            <source type="image/avif" srcset="{{ $srcset('avif') }}" sizes="{{ $width }}px">
            <source type="image/webp" srcset="{{ $srcset('webp') }}" sizes="{{ $width }}px">
            <img src="{{ $path }}-640.webp"
                 alt="{{ $alt }}"
                 width="1320"
                 height="2868"
                 class="block w-full rounded-[1.85rem]"
                 @if ($eager)
                     loading="eager" fetchpriority="high"
                 @else
                     loading="lazy"
                 @endif
                 decoding="async">
        </picture>
    </div>
</div>
