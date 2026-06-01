<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_entry_title_and_html_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();

        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'title' => 'Secret thoughts',
            'html' => '<p>Sensitive content</p>',
        ]);

        $raw = DB::table('entries')->where('id', $entry->id)->first();

        $this->assertNotSame('Secret thoughts', $raw->title);
        $this->assertNotSame('<p>Sensitive content</p>', $raw->html);
        $this->assertStringStartsWith('eyJ', $raw->title);
        $this->assertStringStartsWith('eyJ', $raw->html);
    }

    public function test_entry_returns_plaintext_when_read_via_model(): void
    {
        $user = User::factory()->create();

        Entry::factory()->create([
            'user_id' => $user->id,
            'title' => 'Secret thoughts',
            'html' => '<p>Sensitive content</p>',
        ]);

        $entry = Entry::query()->first();

        $this->assertSame('Secret thoughts', $entry->title);
        $this->assertSame('<p>Sensitive content</p>', $entry->html);
    }

    public function test_entry_non_sensitive_columns_are_plaintext(): void
    {
        $user = User::factory()->create();

        $entry = Entry::factory()->create([
            'user_id' => $user->id,
            'mood' => 'calm',
            'latitude' => 12.34,
            'longitude' => 56.78,
        ]);

        $raw = DB::table('entries')->where('id', $entry->id)->first();

        $this->assertSame('calm', $raw->mood);
        $this->assertEqualsWithDelta(12.34, (float) $raw->latitude, 0.0001);
        $this->assertEqualsWithDelta(56.78, (float) $raw->longitude, 0.0001);
    }

    public function test_quest_title_and_description_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();

        $quest = \App\Models\Quest::factory()->create([
            'user_id' => $user->id,
            'title' => 'Run a marathon',
            'description' => 'My private reason',
        ]);

        $raw = DB::table('quests')->where('id', $quest->id)->first();

        $this->assertStringStartsWith('eyJ', $raw->title);
        $this->assertStringStartsWith('eyJ', $raw->description);
        $this->assertSame('active', $raw->status);
    }

    public function test_character_name_relationship_note_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();

        $character = \App\Models\Character::factory()->create([
            'user_id' => $user->id,
            'name' => 'Alice',
            'relationship' => 'friend',
            'note' => 'Met in Paris',
        ]);

        $raw = DB::table('characters')->where('id', $character->id)->first();

        $this->assertStringStartsWith('eyJ', $raw->name);
        $this->assertStringStartsWith('eyJ', $raw->relationship);
        $this->assertStringStartsWith('eyJ', $raw->note);
    }
}
