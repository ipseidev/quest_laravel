<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\EntryAudio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EntryAudio>
 */
class EntryAudioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'entry_id' => Entry::factory(),
            'uri' => '',
            'remote_uri' => null,
            'duration_ms' => fake()->numberBetween(1000, 120000),
            'waveform' => array_map(fn () => fake()->randomFloat(2, 0, 1), range(1, 50)),
            'is_deleted' => false,
        ];
    }
}
