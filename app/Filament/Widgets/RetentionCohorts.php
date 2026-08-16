<?php

namespace App\Filament\Widgets;

use App\Services\Admin\Metrics;
use Filament\Widgets\Widget;

/**
 * Weekly signup cohorts against the share still syncing in each later week.
 *
 * The number that decides whether paid acquisition is worth starting. Buying
 * installs into a product whose week-4 retention is near zero converts budget
 * into nothing, and no amount of store-listing work fixes it — which is why this
 * sits on the same screen as the acquisition charts rather than in a separate
 * product section.
 */
class RetentionCohorts extends Widget
{
    protected static ?int $sort = 6;

    protected string $view = 'filament.widgets.retention-cohorts';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['cohorts' => app(Metrics::class)->retentionCohorts()];
    }
}
