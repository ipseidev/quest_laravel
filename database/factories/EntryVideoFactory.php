<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\EntryVideo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EntryVideo>
 */
class EntryVideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'entry_id' => Entry::factory(),
            'uri' => '',
            'remote_uri' => null,
            'duration_ms' => fake()->numberBetween(1000, 60000),
            'width' => 1280,
            'height' => 720,
            'is_deleted' => false,
        ];
    }
}
