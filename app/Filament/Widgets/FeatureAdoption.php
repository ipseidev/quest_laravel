<?php

namespace App\Filament\Widgets;

use App\Services\Admin\Metrics;
use Filament\Widgets\Widget;

/**
 * Share of accounts that have ever touched each feature.
 *
 * Read this next to the store listing: a feature high in this list and absent
 * from the screenshots is free conversion left on the table, and one sold in the
 * first screenshot but sitting near the bottom is a promise the app is not
 * keeping.
 */
class FeatureAdoption extends Widget
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.widgets.feature-adoption';

    protected int|string|array $columnSpan = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['features' => app(Metrics::class)->featureAdoption()];
    }
}
