<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Feature adoption</x-slot>
        <x-slot name="description">Share of all accounts that ever used each</x-slot>

        <div class="space-y-3">
            @foreach ($features as $feature)
                <div class="flex items-center gap-3">
                    <span class="w-28 shrink-0 text-sm text-gray-950 dark:text-white">
                        {{ $feature['feature'] }}
                    </span>

                    <div class="h-2 flex-1 rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-2 rounded-full bg-sky-500"
                             style="width: {{ max($feature['share'], 0.5) }}%"></div>
                    </div>

                    <span class="w-24 shrink-0 text-right text-sm tabular-nums text-gray-500 dark:text-gray-400">
                        {{ number_format($feature['users']) }} · {{ $feature['share'] }}%
                    </span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
