{{--
    The download call to action.

    A store with no configured URL renders as a muted "coming soon" chip instead
    of a link, so this component stays correct as each platform goes live — the
    only change needed is filling in `site.stores.*.url`.

    These are typeset buttons rather than the official App Store / Google Play
    badge artwork, which is licensed and has to be obtained from Apple and Google
    directly. Drop those files in and swap the label for an <img> when you have
    them; the layout does not depend on which it is.
--}}
@props(['align' => 'start'])

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
        @else
            <x-site.button variant="muted">
                {{ __('marketing.common.soon_ios') }}
            </x-site.button>
        @endif

        @if ($stores['android']['url'])
            <x-site.button :href="$stores['android']['url']" variant="secondary" external>
                {{ __('marketing.common.download_android') }}
            </x-site.button>
        @else
            <x-site.button variant="muted">
                {{ __('marketing.common.soon_android') }}
            </x-site.button>
        @endif
    </div>

    @if ($stores['ios']['url'] && $stores['previous_name'])
        {{-- The listing still carries the pre-rename title. Saying so up front is
             cheaper than losing the visitor at the App Store page. --}}
        <p class="text-sm text-faint {{ $align === 'center' ? 'text-center' : '' }}">
            {{ __('marketing.common.previous_name_note', ['store_name' => $stores['previous_name']]) }}
        </p>
    @endif
</div>
