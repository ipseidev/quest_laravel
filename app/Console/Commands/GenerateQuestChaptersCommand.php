<?php

namespace App\Console\Commands;

use App\Jobs\GenerateQuestChapter;
use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateQuestChaptersCommand extends Command
{
    protected $signature = 'quest:generate-quest-chapters
        {--quest= : Limit to a single quest id}
        {--user= : Limit to a single user id}';

    protected $description = 'Generate a closing narrative chapter for each completed quest that lacks one.';

    public function handle(): int
    {
        if (! config('services.anthropic.chapters_enabled')) {
            $this->warn('Chapter generation is disabled (set QUEST_CHAPTERS_ENABLED=true to enable).');

            return self::SUCCESS;
        }

        // Completed, non-deleted quests that (a) don't already have a ready quest
        // chapter, (b) have enough linked entries to tell an honest arc, and (c)
        // belong to a user who opted into the AI layer (consent gate — enforced
        // again in ChapterGenerator as defense-in-depth).
        $quests = DB::table('quests')
            ->join('users', 'users.id', '=', 'quests.user_id')
            ->where('users.ai_chapters_opt_in', true)
            ->where('quests.status', 'completed')
            ->where('quests.is_deleted', false)
            ->when($this->option('user'), fn ($query, $id) => $query->where('quests.user_id', $id))
            ->when($this->option('quest'), fn ($query, $id) => $query->where('quests.id', $id))
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('chapters')
                    ->whereColumn('chapters.quest_id', 'quests.id')
                    ->where('chapters.kind', 'quest')
                    ->where('chapters.status', 'ready');
            })
            ->join('entry_quests', 'entry_quests.quest_id', '=', 'quests.id')
            ->join('entries', function ($join) {
                $join->on('entries.id', '=', 'entry_quests.entry_id')
                    ->where('entries.is_deleted', false);
            })
            ->groupBy('quests.id', 'quests.user_id')
            ->havingRaw('count(distinct entries.id) >= ?', [ChapterGenerator::MIN_QUEST_ENTRIES])
            ->pluck('quests.user_id', 'quests.id');

        foreach ($quests as $questId => $userId) {
            $user = User::find($userId);

            if ($user !== null) {
                GenerateQuestChapter::dispatch($user, $questId);
            }
        }

        $this->info("Dispatched {$quests->count()} quest chapter job(s).");

        return self::SUCCESS;
    }
}
