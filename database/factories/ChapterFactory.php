<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    public function definition(): array
    {
        $start = Carbon::parse('2026-03-01')->startOfMonth();

        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'kind' => 'monthly',
            'period_start' => $start,
            'period_end' => $start->copy()->endOfMonth(),
            'quest_id' => null,
            'register' => 'neutral',
            'title' => fake()->sentence(3),
            'body' => ['paragraphs' => [['text' => fake()->paragraph(), 'entryRefs' => []]]],
            'threads' => [],
            'status' => 'ready',
        ];
    }
}
