{{--
    Site footer.

    Doubles as the internal-linking layer: every page in the registry is reachable
    from every other page, which is how a small site gets fully crawled without a
    single orphan.

    The legal column links the pages LegalController serves. Those keep their
    `?lang=` URLs — they were filed in App Store Connect and are linked from the
    app's About screen, so they cannot move — and SiteMap builds the right variant
    for the current language.
--}}
@php
    use App\Support\SiteMap;

    $legal = config('legal');

    $columns = [
        'product' => ['features.quests', 'features.people', 'features.chapters', 'features.constellation'],
        'company' => ['pricing', 'faq', 'about', 'press', 'download'],
        'legal' => ['legal.privacy', 'legal.terms', 'legal.support', 'legal.notice'],
    ];
@endphp

<footer class="border-t border-line bg-night-deep">
    <div class="container-page py-16">
        <div class="grid gap-12 md:grid-cols-[1.3fr_1fr_1fr_1fr]">
            <div>
                <a href="{{ SiteMap::path('home', $locale) }}"
                   class="flex items-center gap-2.5 text-lg font-semibold"
                   style="font-family: var(--font-display)">
                    <img src="/img/pearl-96.png" alt="" width="28" height="28" class="size-7" aria-hidden="true">
                    <span>Nacre</span>
                </a>
                <p class="mt-4 max-w-xs text-sm text-muted">{{ __('marketing.footer.tagline') }}</p>
            </div>

            @foreach ($columns as $name => $keys)
                <nav aria-label="{{ __("marketing.footer.{$name}") }}">
                    <h2 class="mb-4 text-sm font-semibold tracking-[0.1em] text-faint uppercase"
                        style="font-family: var(--font-sans)">
                        {{ __("marketing.footer.{$name}") }}
                    </h2>
                    <ul class="space-y-2.5 text-sm">
                        @foreach ($keys as $key)
                            <li>
                                <a href="{{ SiteMap::path($key, $locale) }}"
                                   class="text-muted transition-colors hover:text-accent-soft">
                                    {{ __("marketing.{$key}.short") }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach
        </div>

        <div class="mt-14 flex flex-col gap-4 border-t border-line pt-8 text-sm text-faint sm:flex-row sm:items-center sm:justify-between">
            <p>
                {{ __('marketing.footer.publisher', [
                    'publisher' => $legal['publisher']['name'],
                    'siren' => $legal['publisher']['siren'],
                ]) }}
                <a href="mailto:{{ $legal['contact_email'] }}"
                   class="text-muted transition-colors hover:text-accent-soft">{{ $legal['contact_email'] }}</a>
            </p>

            <p class="flex items-center gap-2">
                @foreach ($alternates as $altLocale => $altUrl)
                    @if ($altLocale === $locale)
                        <span aria-current="true" class="text-paper uppercase">{{ $altLocale }}</span>
                    @else
                        <a href="{{ $altUrl }}" hreflang="{{ $altLocale }}" lang="{{ $altLocale }}"
                           class="uppercase transition-colors hover:text-accent-soft">{{ $altLocale }}</a>
                    @endif
                @endforeach
            </p>
        </div>
    </div>
</footer>
