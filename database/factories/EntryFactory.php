<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'html' => '<p>'.fake()->paragraph().'</p>',
            'mood' => fake()->randomElement(['empty', 'sad', 'stressed', 'angry', 'anxious', 'calm', 'grateful', 'joyful']),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'entry_date' => now(),
            'is_deleted' => false,
        ];
    }
}
