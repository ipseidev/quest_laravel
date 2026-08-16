<?php

namespace App\Filament\Widgets;

use App\Services\Admin\Metrics;
use Filament\Widgets\ChartWidget;

/**
 * Accounts the server heard from each day.
 *
 * Derived from `personal_access_tokens.last_used_at`, so it measures syncs rather
 * than sessions: an app that wakes in the background to push changes counts, and
 * someone writing offline all week does not until they reconnect. For an
 * offline-first journal that is the closest honest proxy available, and it is
 * stated in the heading rather than left for someone to infer.
 */
class ActivityChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Accounts syncing per day';

    protected ?string $maxHeight = '260px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '14' => 'Last 14 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $rows = app(Metrics::class)->activeByDay((int) $this->filter);

        return [
            'datasets' => [
                [
                    'label' => 'Active accounts',
                    'data' => $rows->pluck('active')->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $rows->pluck('date')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
