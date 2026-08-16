<?php

namespace App\Filament\Widgets;

use App\Services\Admin\Metrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The row you read first. Every figure is the last 30 days against the 30 before
 * it, because a raw count on a product this young says almost nothing on its own.
 */
class OverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public function getStats(): array
    {
        $metrics = app(Metrics::class);
        $overview = $metrics->overview();
        $currency = config('site.plus.currency_symbol');

        return [
            Stat::make('Accounts', number_format($overview['total_accounts']))
                ->description('All time')
                ->color('gray'),

            Stat::make('Signups', number_format($overview['signups']))
                ->description($this->trend($overview['signups'], $overview['signups_previous']))
                ->descriptionIcon($this->arrow($overview['signups'], $overview['signups_previous']))
                ->color($this->tone($overview['signups'], $overview['signups_previous'])),

            Stat::make('Active accounts', number_format($overview['active']))
                ->description($this->trend($overview['active'], $overview['active_previous']).' · synced in 30d')
                ->descriptionIcon($this->arrow($overview['active'], $overview['active_previous']))
                ->color($this->tone($overview['active'], $overview['active_previous'])),

            Stat::make('Activation', $overview['activation_rate'].'%')
                ->description('Accounts that ever wrote a page')
                ->color($overview['activation_rate'] >= 50 ? 'success' : 'warning'),

            Stat::make('Plus subscribers', number_format($overview['subscribers']))
                ->description('Entitlement not expired')
                ->color('gray'),

            Stat::make('MRR', $currency.number_format($overview['mrr'], 2))
                ->description($this->revenueNote($overview))
                ->color($overview['unmapped_products'] === [] ? 'success' : 'danger'),
        ];
    }

    /**
     * MRR is only as trustworthy as `site.plus.products`, so an unpriced product id
     * is stated on the stat itself rather than left to be discovered later.
     *
     * @param  array<string, mixed>  $overview
     */
    private function revenueNote(array $overview): string
    {
        $unmapped = $overview['unmapped_products'];

        if ($unmapped === []) {
            return config('site.plus.currency_symbol').number_format($overview['arr'], 0).' ARR';
        }

        return array_sum($unmapped).' subscriber(s) on an unpriced product: '
            .implode(', ', array_keys($unmapped));
    }

    private function trend(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current === 0 ? 'No change' : 'First period with any';
        }

        $delta = round(($current - $previous) / $previous * 100);

        return sprintf('%+d%% vs previous 30d', $delta);
    }

    private function arrow(int $current, int $previous): ?string
    {
        return match (true) {
            $current > $previous => 'heroicon-m-arrow-trending-up',
            $current < $previous => 'heroicon-m-arrow-trending-down',
            default => null,
        };
    }

    private function tone(int $current, int $previous): string
    {
        return match (true) {
            $current > $previous => 'success',
            $current < $previous => 'danger',
            default => 'gray',
        };
    }
}
