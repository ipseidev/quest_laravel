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

class GenerateAnnualChapter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry transient Anthropic failures (ChapterGenerationException) a few times. */
    public int $tries = 4;

    public function __construct(
        public User $user,
        public int $year,
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
        $generator->annual($this->user, $this->year);
    }

    public function failed(Throwable $e): void
    {
        Log::error('quest.chapter.annual_failed', [
            'user_id' => $this->user->id,
            'year' => $this->year,
            'message' => $e->getMessage(),
        ]);
    }
}
