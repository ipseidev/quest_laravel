<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateMonthlyChapter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry transient Anthropic failures (ChapterGenerationException) a few times. */
    public int $tries = 4;

    public function __construct(
        public User $user,
        public CarbonInterface $monthStart,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ChapterGenerator $generator): void
    {
        // A null return means "nothing to generate" (skip/refusal/unparsable) — done.
        // A thrown ChapterGenerationException means transient — the queue retries.
        $generator->monthly($this->user, $this->monthStart);
    }

    public function failed(Throwable $e): void
    {
        Log::error('quest.chapter.monthly_failed', [
            'user_id' => $this->user->id,
            'month' => $this->monthStart->format('Y-m'),
            'message' => $e->getMessage(),
        ]);
    }
}
