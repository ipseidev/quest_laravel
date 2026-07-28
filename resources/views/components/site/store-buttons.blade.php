{{--
    The download call to action.

    A store with no configured URL renders as a muted "coming soon" chip instead
    of a link, so this component stays correct as each platform goes live — the
    only change needed is filling in `site.stores.*.url`.

    These are typeset buttons rather than the official App Store / Google Play
    badge artwork, which is licensed and has to be obtained from Apple and Google
    directly. Drop those files in and swap the label for an <img> when you have
    them; the layout does not depend on which it is.

    `note` exists because the transitional "the listing is still called Quest"
    line belongs at the bottom of a page, not directly under the first button a
    visitor sees. Repeated at every call site it turns the highest-attention
    pixels of the page into a caveat.

    `soon` decides whether a store with no public listing still gets its greyed
    chip. It stays on everywhere the question "which platforms?" is what the
    visitor came to resolve — the download page, the closing call to action — and
    is turned off in the home hero, where the chip is a non-tappable control
    sitting beside the real one, costing ~58 px of the mobile first screen that
    the app screenshot needs. Turn it back on by dropping the prop.
--}}
@props(['align' => 'start', 'note' => true, 'soon' => true])

@php
    $stores = config('site.stores');
    $alignment = $align === 'center' ? 'justify-center' : 'justify-start';
@endphp

<div class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center gap-3 {{ $alignment }}">
        @if ($stores['ios']['url'])
            <x-site.button :href="$stores['ios']['url']" external>
                {{ __('marketing.common.download_ios') }}
            </x-site.button>
        @elseif ($soon)
            <x-site.button variant="muted">
                {{ __('marketing.common.soon_ios') }}
            </x-site.button>
        @endif

        @if ($stores['android']['url'])
            <x-site.button :href="$stores['android']['url']" variant="secondary" external>
                {{ __('marketing.common.download_android') }}
            </x-site.button>
        @elseif ($soon)
            <x-site.button variant="muted">
                {{ __('marketing.common.soon_android') }}
            </x-site.button>
        @endif
    </div>

    @if ($note && $stores['ios']['url'] && $stores['previous_name'])
        {{-- The listing still carries the pre-rename title. Saying so up front is
             cheaper than losing the visitor at the App Store page. --}}
        <p class="text-sm text-faint {{ $align === 'center' ? 'text-center' : '' }}">
            {{ __('marketing.common.previous_name_note', ['store_name' => $stores['previous_name']]) }}
        </p>
    @endif
</div>
