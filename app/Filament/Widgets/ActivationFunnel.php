<?php

namespace App\Filament\Widgets;

use App\Services\Admin\Metrics;
use Filament\Widgets\Widget;

/**
 * Where accounts created in the window stopped.
 *
 * The last step — linking a page to a quest or a person — is the one that matters
 * commercially. It is the only thing Nacre does that a plain notes app does not,
 * so an account that never links has no reason to stay or to pay, however many
 * pages it wrote.
 */
class ActivationFunnel extends Widget
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.activation-funnel';

    protected int|string|array $columnSpan = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['steps' => app(Metrics::class)->activationFunnel()];
    }
}
