<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\EntryAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EntryAttachment>
 */
class EntryAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'entry_id' => Entry::factory(),
            'uri' => '',
            'remote_uri' => null,
            'width' => fake()->numberBetween(100, 4000),
            'height' => fake()->numberBetween(100, 4000),
            'is_deleted' => false,
        ];
    }
}
