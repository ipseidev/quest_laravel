<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Weekly retention cohorts</x-slot>
        <x-slot name="description">
            Share of each signup week still syncing N weeks later. Read down a column:
            comparing two cohorts of different ages mostly measures how long each has had to churn.
        </x-slot>

        @if (count($cohorts) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">No signups yet in the window.</p>
        @else
            {{-- The grid is wider than a phone and must scroll inside itself rather
                 than making the whole dashboard scroll sideways. --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4 font-medium">Cohort</th>
                            <th class="py-2 pr-4 font-medium text-right">Size</th>
                            @for ($w = 0; $w < count($cohorts[0]['weeks']); $w++)
                                <th class="py-2 pr-3 font-medium text-right tabular-nums">W{{ $w }}</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cohorts as $cohort)
                            <tr class="border-t border-gray-100 dark:border-white/10">
                                <td class="py-2 pr-4 whitespace-nowrap text-gray-950 dark:text-white">
                                    {{ \Illuminate\Support\Carbon::parse($cohort['cohort'])->isoFormat('D MMM') }}
                                </td>
                                <td class="py-2 pr-4 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ $cohort['size'] }}
                                </td>

                                @foreach ($cohort['weeks'] as $share)
                                    @if ($share === null)
                                        {{-- Weeks this cohort has not lived through yet: blank,
                                             never 0%, which would read as total churn. --}}
                                        <td class="py-2 pr-3 text-right text-gray-300 dark:text-gray-600">—</td>
                                    @else
                                        <td class="py-2 pr-3 text-right tabular-nums"
                                            style="background-color: rgba(245, 158, 11, {{ round($share / 100 * 0.35, 3) }})">
                                            {{ $share }}%
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
