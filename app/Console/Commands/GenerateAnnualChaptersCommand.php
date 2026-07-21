<?php

namespace App\Console\Commands;

use App\Jobs\GenerateAnnualChapter;
use App\Models\Entry;
use App\Models\Scopes\BelongsToCurrentUserScope;
use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateAnnualChaptersCommand extends Command
{
    protected $signature = 'quest:generate-annual-chapters
        {--year= : Target year as YYYY (defaults to last year)}
        {--user= : Limit to a single user id}';

    protected $description = 'Generate an annual narrative chapter for eligible users.';

    public function handle(): int
    {
        if (! config('services.anthropic.chapters_enabled')) {
            $this->warn('Chapter generation is disabled (set QUEST_CHAPTERS_ENABLED=true to enable).');

            return self::SUCCESS;
        }

        $year = $this->option('year') ? (int) $this->option('year') : (int) now()->subYear()->year;
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = $start->copy()->endOfYear();

        $userIds = Entry::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('is_deleted', false)
            ->whereBetween(DB::raw('COALESCE(entry_date, created_at)'), [$start, $end])
            ->when($this->option('user'), fn ($query, $id) => $query->where('user_id', $id))
            ->groupBy('user_id')
            ->havingRaw('count(*) >= ?', [ChapterGenerator::MIN_ANNUAL_ENTRIES])
            ->pluck('user_id');

        // Consent gate: only opted-in users (enforced again in ChapterGenerator)
        // who don't already have this year's chapter — idempotent at dispatch, so a
        // re-run or a double-fire of the yearly schedule never queues no-op jobs.
        $users = User::query()
            ->whereIn('id', $userIds)
            ->where('ai_chapters_opt_in', true)
            ->whereNotExists(function ($query) use ($start) {
                $query->select(DB::raw(1))
                    ->from('chapters')
                    ->whereColumn('chapters.user_id', 'users.id')
                    ->where('chapters.kind', 'annual')
                    ->where('chapters.status', 'ready')
                    ->where('chapters.period_start', '>=', $start)
                    ->where('chapters.period_start', '<', $start->copy()->addYear());
            })
            ->get();

        foreach ($users as $user) {
            GenerateAnnualChapter::dispatch($user, $year);
        }

        $this->info("Dispatched {$users->count()} annual chapter job(s) for {$year}.");

        return self::SUCCESS;
    }
}
