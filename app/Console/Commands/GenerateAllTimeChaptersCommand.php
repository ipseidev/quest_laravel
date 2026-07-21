<?php

namespace App\Console\Commands;

use App\Jobs\GenerateAllTimeChapter;
use App\Models\Entry;
use App\Models\Scopes\BelongsToCurrentUserScope;
use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateAllTimeChaptersCommand extends Command
{
    protected $signature = 'quest:generate-alltime-chapters
        {--user= : Limit to a single user id}
        {--force : Regenerate, replacing an existing all-time chapter}';

    protected $description = 'Generate the all-time ("since the beginning") narrative chapter for eligible users. On-demand — not scheduled.';

    public function handle(): int
    {
        if (! config('services.anthropic.chapters_enabled')) {
            $this->warn('Chapter generation is disabled (set QUEST_CHAPTERS_ENABLED=true to enable).');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        $userIds = Entry::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('is_deleted', false)
            ->when($this->option('user'), fn ($query, $id) => $query->where('user_id', $id))
            ->groupBy('user_id')
            ->havingRaw('count(*) >= ?', [ChapterGenerator::MIN_ALLTIME_ENTRIES])
            ->pluck('user_id');

        // Consent gate (enforced again in ChapterGenerator). Without --force, skip
        // users who already have an all-time chapter so a plain re-run is a no-op;
        // with --force, dispatch for all eligible users so each is regenerated.
        $users = User::query()
            ->whereIn('id', $userIds)
            ->where('ai_chapters_opt_in', true)
            ->when(! $force, function ($query) {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('chapters')
                        ->whereColumn('chapters.user_id', 'users.id')
                        ->where('chapters.kind', 'alltime')
                        ->where('chapters.status', 'ready');
                });
            })
            ->get();

        foreach ($users as $user) {
            GenerateAllTimeChapter::dispatch($user, $force);
        }

        $this->info("Dispatched {$users->count()} all-time chapter job(s)".($force ? ' (force regenerate)' : '').'.');

        return self::SUCCESS;
    }
}
