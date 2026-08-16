@php
    // Defined once so every block below renders a label/number pair identically.
    $row = fn (string $label, string $value) => new \Illuminate\Support\HtmlString(
        '<div class="flex items-baseline justify-between gap-3 py-1.5 text-sm">'
        .'<span class="text-gray-500 dark:text-gray-400">'.e($label).'</span>'
        .'<span class="tabular-nums font-medium text-gray-950 dark:text-white">'.e($value).'</span>'
        .'</div>'
    );
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Audience</x-slot>

        <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">

            <div>
                <h3 class="mb-1 text-sm font-semibold text-gray-950 dark:text-white">Platform</h3>
                {{ $row('iOS', number_format($platforms['ios'])) }}
                {{ $row('Android', number_format($platforms['android'])) }}
                {{ $row('Not yet reported', number_format($platforms['unknown'])) }}

                @if ($platforms['unknown'] > 0)
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Accounts created before device reporting shipped, or still on a build that
                        predates it. They are counted separately rather than assumed to be iOS.
                    </p>
                @endif
            </div>

            <div>
                <h3 class="mb-1 text-sm font-semibold text-gray-950 dark:text-white">
                    Installed versions
                </h3>

                @forelse ($versions as $version)
                    {{ $row(
                        $version->app_version.' · '.($version->platform ?? 'unknown'),
                        number_format($version->users),
                    ) }}
                @empty
                    <p class="py-1.5 text-sm text-gray-500 dark:text-gray-400">
                        Nothing reported yet — starts filling in once a build that sends
                        <code>appVersion</code> reaches the stores.
                    </p>
                @endforelse
            </div>

            <div>
                <h3 class="mb-1 text-sm font-semibold text-gray-950 dark:text-white">Language</h3>
                @foreach ($locales as $locale => $count)
                    {{ $row(strtoupper($locale), number_format($count)) }}
                @endforeach
            </div>

            <div>
                <h3 class="mb-1 text-sm font-semibold text-gray-950 dark:text-white">Sign-in method</h3>
                {{ $row('Apple', number_format($auth['apple'])) }}
                {{ $row('Google', number_format($auth['google'])) }}
                {{ $row('Email + password', number_format($auth['password'])) }}
                {{ $row('Reachable by email', number_format($auth['reachable_by_email'])) }}
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    The last line is the ceiling on any lifecycle email: Apple relay addresses and
                    accounts created without one are not in it.
                </p>
            </div>

            <div>
                <h3 class="mb-1 text-sm font-semibold text-gray-950 dark:text-white">Subscriptions</h3>
                {{ $row('Active', number_format($subscriptions['active'])) }}
                {{ $row('Expiring within 30d', number_format($subscriptions['expiring_30d'])) }}
                {{ $row('Lapsed', number_format($subscriptions['lapsed'])) }}
                {{ $row('AI chapters opted in', number_format($subscriptions['ai_opt_in'])) }}
            </div>

            <div>
                <h3 class="mb-1 text-sm font-semibold text-gray-950 dark:text-white">Storage</h3>
                {{ $row('Total stored', $storageGb.' GB') }}
                {{ $row('Free accounts past 80% of quota', number_format($storage['free_near_quota'])) }}
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    The second line is the closest thing to a warm upgrade list: free accounts that
                    are about to hit the media cap Plus removes.
                </p>
            </div>

        </div>
    </x-filament::section>
</x-filament-widgets::widget>
