<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates the ONE complimentary all-time Chapter a free account may request
 * (MONETIZATION_PLAN P1.3) — the conversion hook before Nacre Plus. The caller
 * (AiChapterController::sample) has already stamped `sample_chapter_generated_at`
 * as an optimistic lock against a double request; if nothing is actually
 * produced (a model refusal, or a hard failure after retries) we CLEAR that
 * stamp so the user isn't left having "spent" their sample on nothing.
 */
class GenerateSampleChapter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry transient Anthropic failures (ChapterGenerationException) a few times. */
    public int $tries = 4;

    public function __construct(public User $user) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ChapterGenerator $generator): void
    {
        $chapter = $generator->allTime($this->user);

        if ($chapter === null) {
            // Refused / too thin / already exists → free the sample for a retry.
            $this->releaseSample();
        }
    }

    public function failed(Throwable $e): void
    {
        // Total failure after retries — free the sample so the user can try again.
        $this->releaseSample();

        Log::error('quest.chapter.sample_failed', [
            'user_id' => $this->user->id,
            'message' => $e->getMessage(),
        ]);
    }

    private function releaseSample(): void
    {
        $this->user->forceFill(['sample_chapter_generated_at' => null])->save();
    }
}
