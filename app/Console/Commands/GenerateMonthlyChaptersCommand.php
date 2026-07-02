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
        {--month= : Target month as YYYY-MM (defaults to last month)}
        {--user= : Limit to a single user id}';

    protected $description = 'Generate monthly narrative chapters for eligible users.';

    public function handle(): int
    {
        if (! config('services.anthropic.chapters_enabled')) {
            $this->warn('Chapter generation is disabled (set QUEST_CHAPTERS_ENABLED=true to enable).');

            return self::SUCCESS;
        }

        $month = $this->option('month')
            ? Carbon::parse($this->option('month').'-01')->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $end = $month->copy()->endOfMonth();

        // NOTE: production must also restrict this to users who opted into the AI layer.
        // That consent flag is not yet modelled server-side — wire it in here before shipping.
        $userIds = Entry::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('is_deleted', false)
            ->whereBetween(DB::raw('COALESCE(entry_date, created_at)'), [$month, $end])
            ->when($this->option('user'), fn ($query, $id) => $query->where('user_id', $id))
            ->groupBy('user_id')
            ->havingRaw('count(*) >= ?', [ChapterGenerator::MIN_ENTRIES])
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = User::find($userId);

            if ($user !== null) {
                GenerateMonthlyChapter::dispatch($user, $month);
            }
        }

        $this->info("Dispatched {$userIds->count()} monthly chapter job(s) for {$month->format('Y-m')}.");

        return self::SUCCESS;
    }
}
