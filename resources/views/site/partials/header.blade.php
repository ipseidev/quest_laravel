{{--
    Site header.

    The mobile menu is a <details>/<summary> pair, not a scripted panel: it opens,
    closes, traps nothing, announces its own expanded state to a screen reader,
    and works before any JavaScript would have loaded — because there is none.

    The language switch links to the *same page* in the other language via the
    alternates the controller resolved, never to that language's home page. A
    switcher that dumps you back at the root is the fastest way to lose a reader
    mid-page.
--}}
@php
    use App\Support\SiteMap;

    $navKeys = ['features', 'pricing', 'privacy', 'faq'];
    $activeTop = explode('.', $pageKey)[0];
@endphp

<header class="sticky top-0 z-40 border-b border-line/70 bg-ink/85 backdrop-blur-md">
    <div class="container-page flex h-16 items-center justify-between gap-4">
        <a href="{{ SiteMap::path('home', $locale) }}"
           class="flex shrink-0 items-center gap-2.5 text-lg font-semibold"
           style="font-family: var(--font-display)"
           @if ($pageKey === 'home') aria-current="page" @endif>
            <img src="/img/pearl-96.png" alt="" width="28" height="28" class="size-7" aria-hidden="true">
            <span>Nacre</span>
        </a>

        <nav class="hidden items-center gap-7 text-[0.9375rem] md:flex" aria-label="{{ __('marketing.nav.label') }}">
            @foreach ($navKeys as $key)
                <a href="{{ SiteMap::path($key, $locale) }}"
                   class="transition-colors hover:text-accent-soft {{ $activeTop === $key ? 'text-accent-soft' : 'text-muted' }}"
                   @if ($activeTop === $key) aria-current="page" @endif>
                    {{ __("marketing.{$key}.short") }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            @foreach ($alternates as $altLocale => $altUrl)
                @if ($altLocale !== $locale)
                    <a href="{{ $altUrl }}"
                       hreflang="{{ $altLocale }}"
                       lang="{{ $altLocale }}"
                       class="rounded-full border border-line px-3 py-1.5 text-xs font-semibold tracking-wide text-muted uppercase transition-colors hover:border-line-bright hover:text-paper">
                        <span class="sr-only">{{ __('marketing.nav.switch_language') }}</span>{{ $altLocale }}
                    </a>
                @endif
            @endforeach

            {{-- Hidden on the wrapper, not on the button. `hidden` on the button
                 itself loses to the `inline-flex` in its own base classes — same CSS
                 layer, so stylesheet order decides and the class attribute's order is
                 irrelevant. Left on the button, it stayed visible below `sm` and
                 pushed the document wider than the viewport. --}}
            <div class="hidden sm:block">
                <x-site.button :href="SiteMap::path('download', $locale)" class="px-5! py-2!">
                    {{ __('marketing.nav.download') }}
                </x-site.button>
            </div>

            <details class="relative md:hidden">
                <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-full border border-line text-muted [&::-webkit-details-marker]:hidden">
                    <span class="sr-only">{{ __('marketing.nav.menu') }}</span>
                    <svg width="18" height="14" viewBox="0 0 18 14" fill="none" aria-hidden="true">
                        <path d="M1 1h16M1 7h16M1 13h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </summary>

                <div class="absolute right-0 z-50 mt-3 w-60 rounded-2xl border border-line bg-surface p-2 shadow-2xl shadow-black/60">
                    @foreach ([...$navKeys, 'about', 'download'] as $key)
                        <a href="{{ SiteMap::path($key, $locale) }}"
                           class="block rounded-xl px-4 py-2.5 text-[0.9375rem] transition-colors hover:bg-surface-raised {{ $activeTop === $key ? 'text-accent-soft' : 'text-muted' }}">
                            {{ __("marketing.{$key}.short") }}
                        </a>
                    @endforeach
                </div>
            </details>
        </div>
    </div>
</header>
