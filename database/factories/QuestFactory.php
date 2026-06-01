<?php

namespace Database\Factories;

use App\Models\Quest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quest>
 */
class QuestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['main', 'side', 'daily']),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => 'active',
            'color' => fake()->hexColor(),
            'icon' => null,
            'started_at' => now(),
            'completed_at' => null,
            'is_deleted' => false,
        ];
    }
}
