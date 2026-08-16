<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Activation funnel</x-slot>
        <x-slot name="description">Accounts created in the last 30 days</x-slot>

        <div class="space-y-4">
            @foreach ($steps as $step)
                <div>
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="font-medium text-gray-950 dark:text-white">{{ $step['step'] }}</span>
                        <span class="tabular-nums text-gray-500 dark:text-gray-400">
                            {{ number_format($step['users']) }}
                            <span class="text-gray-400 dark:text-gray-500">· {{ $step['share'] }}%</span>
                        </span>
                    </div>

                    {{-- Width is the share of the first step, so the bars taper and the
                         drop between two rows is the thing you actually see. --}}
                    <div class="mt-1.5 h-2 rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-2 rounded-full bg-amber-500"
                             style="width: {{ max($step['share'], 0.5) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
