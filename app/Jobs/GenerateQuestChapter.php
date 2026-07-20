<?php

namespace App\Jobs;

use App\Models\Quest;
use App\Models\Scopes\BelongsToCurrentUserScope;
use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateQuestChapter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $questId,
    ) {}

    public function handle(ChapterGenerator $generator): void
    {
        // Carry the id (not the model) so SerializesModels' re-query on the queue —
        // which runs without an authenticated user — doesn't get filtered to nothing
        // by the current-user scope. Re-load explicitly and re-verify ownership.
        $quest = Quest::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $this->user->id)
            ->where('id', $this->questId)
            ->first();

        if ($quest === null) {
            return;
        }

        $generator->questArc($this->user, $quest);
    }
}
