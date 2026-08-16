<?php

namespace App\Filament\Widgets;

use App\Services\Admin\Metrics;
use Filament\Widgets\Widget;

/**
 * The composition of the base: platform, installed versions, language, how people
 * sign in, subscription health and stored bytes.
 *
 * Grouped into one widget because each of these is a handful of numbers that only
 * means something next to the others — the language split decides which store
 * listing to work on, and the auth split caps how much of that audience any email
 * campaign could ever reach.
 */
class AudienceBreakdown extends Widget
{
    protected static ?int $sort = 7;

    protected string $view = 'filament.widgets.audience-breakdown';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $metrics = app(Metrics::class);
        $storage = $metrics->storage();

        return [
            'platforms' => $metrics->platformSplit(),
            'versions' => $metrics->appVersions(),
            'locales' => $metrics->localeSplit(),
            'auth' => $metrics->authMethodSplit(),
            'subscriptions' => $metrics->subscriptionHealth(),
            'storage' => $storage,
            'storageGb' => round($storage['total_bytes'] / 1024 ** 3, 2),
        ];
    }
}
