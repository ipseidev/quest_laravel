<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'relationship' => fake()->word(),
            'note' => fake()->sentence(),
            'photo_uri' => '',
            'remote_photo_uri' => null,
            'color' => fake()->hexColor(),
            'is_deleted' => false,
        ];
    }
}
