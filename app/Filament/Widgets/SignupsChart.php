<?php

namespace App\Filament\Widgets;

use App\Services\Admin\Metrics;
use Filament\Widgets\ChartWidget;

/**
 * Daily signups, stacked by platform.
 *
 * The `Unknown` series is shown, not hidden. It holds every account created
 * before device reporting shipped plus anyone on an older build, and while it is
 * large the iOS/Android comparison is a lower bound rather than a split. Hiding
 * it would turn "we cannot see this yet" into a confident and wrong answer.
 */
class SignupsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Signups by platform';

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
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = app(Metrics::class)->signupsByDay((int) $this->filter);

        return [
            'datasets' => [
                [
                    'label' => 'iOS',
                    'data' => $rows->pluck('ios')->all(),
                    'backgroundColor' => '#38bdf8',
                    'stack' => 'signups',
                ],
                [
                    'label' => 'Android',
                    'data' => $rows->pluck('android')->all(),
                    'backgroundColor' => '#4ade80',
                    'stack' => 'signups',
                ],
                [
                    'label' => 'Unknown',
                    'data' => $rows->pluck('unknown')->all(),
                    'backgroundColor' => '#64748b',
                    'stack' => 'signups',
                ],
            ],
            'labels' => $rows->pluck('date')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                // Signups are whole people; a fractional gridline reads as a bug.
                'y' => ['stacked' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
