<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyChapter;
use App\Models\Entry;
use App\Models\Scopes\BelongsToCurrentUserScope;
use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyChaptersCommand extends Command
{
    protected $signature = 'quest:generate-monthly-chapters
        {--month= : Single target month as YYYY-MM (defaults to last month)}
        {--since= : Backfill from this month (YYYY-MM), inclusive}
        {--until= : Backfill up to this month (YYYY-MM), inclusive; defaults to last month}
        {--user= : Limit to a single user id}';

    protected $description = 'Generate monthly narrative chapters for eligible users.';

    public function handle(): int
    {
        if (! config('services.anthropic.chapters_enabled')) {
            $this->warn('Chapter generation is disabled (set QUEST_CHAPTERS_ENABLED=true to enable).');

            return self::SUCCESS;
        }

        $months = $this->targetMonths();
        $dispatched = 0;

        foreach ($months as $month) {
            $dispatched += $this->dispatchForMonth($month);
        }

        $this->info("Dispatched {$dispatched} monthly chapter job(s) across ".count($months).' month(s).');

        return self::SUCCESS;
    }

    /**
     * The months to (re)generate: a single --month, a --since/--until backfill
     * window, or (default) just last month.
     *
     * @return array<int, Carbon>
     */
    private function targetMonths(): array
    {
        if ($this->option('month')) {
            return [Carbon::parse($this->option('month').'-01')->startOfMonth()];
        }

        if ($this->option('since')) {
            $cursor = Carbon::parse($this->option('since').'-01')->startOfMonth();
            $until = $this->option('until')
                ? Carbon::parse($this->option('until').'-01')->startOfMonth()
                : now()->subMonthNoOverflow()->startOfMonth();

            $months = [];
            while ($cursor->lte($until)) {
                $months[] = $cursor->copy();
                $cursor->addMonth();
            }

            return $months;
        }

        return [now()->subMonthNoOverflow()->startOfMonth()];
    }

    private function dispatchForMonth(Carbon $month): int
    {
        $end = $month->copy()->endOfMonth();
        $nextMonth = $month->copy()->addMonth();

        $eligibleUserIds = Entry::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('is_deleted', false)
            ->whereBetween(DB::raw('COALESCE(entry_date, created_at)'), [$month, $end])
            ->when($this->option('user'), fn ($query, $id) => $query->where('user_id', $id))
            ->groupBy('user_id')
            ->havingRaw('count(*) >= ?', [ChapterGenerator::MIN_ENTRIES])
            ->pluck('user_id');

        // Only opted-in users (consent gate, enforced again in ChapterGenerator)
        // who don't already have a ready chapter for this month — so a re-run or a
        // backfill is idempotent at dispatch time and never queues no-op jobs.
        $users = User::query()
            ->whereIn('id', $eligibleUserIds)
            ->where('ai_chapters_opt_in', true)
            ->whereNotExists(function ($query) use ($month, $nextMonth) {
                $query->select(DB::raw(1))
                    ->from('chapters')
                    ->whereColumn('chapters.user_id', 'users.id')
                    ->where('chapters.kind', 'monthly')
                    ->where('chapters.status', 'ready')
                    ->where('chapters.period_start', '>=', $month)
                    ->where('chapters.period_start', '<', $nextMonth);
            })
            ->get();

        foreach ($users as $user) {
            GenerateMonthlyChapter::dispatch($user, $month);
        }

        return $users->count();
    }
}
