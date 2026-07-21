<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            // Mirrors the production default: users are opted OUT of the AI layer
            // until they explicitly consent. Tests that exercise chapter generation
            // or reads use ->optedIntoAi() to make the consent requirement visible.
            'ai_chapters_opt_in' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has opted into the AI Chapters layer.
     */
    public function optedIntoAi(): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_chapters_opt_in' => true,
        ]);
    }
}
