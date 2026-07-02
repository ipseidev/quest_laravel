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

class GenerateMonthlyChapter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public CarbonInterface $monthStart,
    ) {}

    public function handle(ChapterGenerator $generator): void
    {
        $generator->monthly($this->user, $this->monthStart);
    }
}
