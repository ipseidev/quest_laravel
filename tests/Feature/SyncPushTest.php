<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Entry;
use App\Models\EntryAttachment;
use App\Models\EntryAudio;
use App\Models\Quest;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SyncPushTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('mobile')->plainTextToken;
    }

    private function push(array $changes, ?string $token = null): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.($token ?? $this->token))
            ->postJson('/api/sync/push', [
                'deviceId' => (string) Str::uuid(),
                'changes' => $changes,
            ]);
    }

    private function entryChange(string $id, array $overrides = [], string $operation = 'create'): array
    {
        return [
            'entityType' => 'entry',
            'entityId' => $id,
            'operation' => $operation,
            'data' => array_merge([
                'id' => $id,
                'title' => 'Title',
                'html' => '<p>body</p>',
                'mood' => null,
                'latitude' => null,
                'longitude' => null,
                'entryDate' => null,
                'isDeleted' => false,
                'createdAt' => '2026-05-13T10:00:00.000Z',
                'updatedAt' => '2026-05-13T10:00:00.000Z',
                'syncedAt' => null,
            ], $overrides),
        ];
    }

    private function attachmentChange(string $id, string $entryId, array $overrides = [], string $operation = 'create'): array
    {
        return [
            'entityType' => 'entry_attachment',
            'entityId' => $id,
            'operation' => $operation,
            'data' => array_merge([
                'id' => $id,
                'entryId' => $entryId,
                'uri' => '',
                'remoteUri' => null,
                'width' => 800,
                'height' => 600,
                'isDeleted' => false,
                'createdAt' => '2026-05-13T10:00:00.000Z',
                'updatedAt' => '2026-05-13T10:00:00.000Z',
                'syncedAt' => null,
            ], $overrides),
        ];
    }

    private function junctionChange(string $type, string $entryId, string $otherId, string $operation = 'create'): array
    {
        $otherKey = $type === 'entry_quest' ? 'questId' : 'characterId';

        return [
            'entityType' => $type,
            'entityId' => $entryId.':'.$otherId,
            'operation' => $operation,
            'data' => ['entryId' => $entryId, $otherKey => $otherId],
        ];
    }

    private function quoteChange(string $id, array $overrides = [], string $operation = 'create'): array
    {
        return [
            'entityType' => 'quote',
            'entityId' => $id,
            'operation' => $operation,
            'data' => array_merge([
                'id' => $id,
                'text' => 'Be the change',
                'source' => 'Gandhi',
                'note' => '',
                'isDeleted' => false,
                'createdAt' => '2026-05-13T10:00:00.000Z',
                'updatedAt' => '2026-05-13T10:00:00.000Z',
                'syncedAt' => null,
            ], $overrides),
        ];
    }

    public function test_s1_push_single_entry_to_empty_server(): void
    {
        $id = (string) Str::uuid();

        $this->push([$this->entryChange($id, ['title' => 'Hello', 'mood' => 'calm'])])
            ->assertOk()
            ->assertExactJson(['confirmed' => [$id], 'conflicts' => []]);

        $entry = Entry::query()->find($id);
        $this->assertSame($this->user->id, $entry->user_id);
        $this->assertSame('Hello', $entry->title);
        $this->assertSame('calm', $entry->mood);
    }

    public function test_s4_push_same_entry_twice_is_idempotent(): void
    {
        $id = (string) Str::uuid();
        $change = $this->entryChange($id);

        $this->push([$change])->assertOk();
        $this->push([$change])
            ->assertOk()
            ->assertJsonCount(1, 'confirmed')
            ->assertJsonCount(0, 'conflicts');

        $this->assertDatabaseCount('entries', 1);
    }

    public function test_s5_push_with_older_updated_at_returns_conflict(): void
    {
        $id = (string) Str::uuid();

        $this->push([$this->entryChange($id, ['title' => 'server v', 'updatedAt' => '2026-05-13T10:00:00.000Z'])])
            ->assertOk();

        $this->push([$this->entryChange($id, ['title' => 'older client v', 'updatedAt' => '2026-05-13T09:00:00.000Z'])])
            ->assertOk()
            ->assertJsonCount(0, 'confirmed')
            ->assertJsonCount(1, 'conflicts')
            ->assertJsonPath('conflicts.0.entityType', 'entry')
            ->assertJsonPath('conflicts.0.entityId', $id)
            ->assertJsonPath('conflicts.0.serverVersion.title', 'server v')
            ->assertJsonPath('conflicts.0.serverVersion.updatedAt', '2026-05-13T10:00:00.000Z');

        $this->assertSame('server v', Entry::query()->find($id)->title);
    }

    public function test_s5_equal_updated_at_is_not_a_conflict(): void
    {
        $id = (string) Str::uuid();
        $ts = '2026-05-13T10:00:00.000Z';

        $this->push([$this->entryChange($id, ['title' => 'v1', 'updatedAt' => $ts])])->assertOk();
        $this->push([$this->entryChange($id, ['title' => 'v2', 'updatedAt' => $ts])])
            ->assertOk()
            ->assertJsonCount(1, 'confirmed')
            ->assertJsonCount(0, 'conflicts');

        $this->assertSame('v2', Entry::query()->find($id)->title);
    }

    public function test_s6_soft_delete_entry_marks_is_deleted(): void
    {
        $id = (string) Str::uuid();
        $this->push([$this->entryChange($id)])->assertOk();

        $this->push([
            $this->entryChange($id, ['isDeleted' => true, 'updatedAt' => '2026-05-13T11:00:00.000Z'], 'delete'),
        ])->assertOk()->assertJsonCount(1, 'confirmed');

        $entry = Entry::query()->find($id);
        $this->assertTrue($entry->is_deleted);
    }

    public function test_s11_4_delete_without_create_upserts_with_is_deleted_true(): void
    {
        $id = (string) Str::uuid();

        $this->push([
            $this->entryChange($id, ['isDeleted' => true], 'delete'),
        ])->assertOk()->assertJsonCount(1, 'confirmed');

        $entry = Entry::query()->find($id);
        $this->assertNotNull($entry);
        $this->assertTrue($entry->is_deleted);
    }

    public function test_j1_push_same_junction_twice_yields_one_row(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $quest = Quest::factory()->for($this->user)->create();

        $change = $this->junctionChange('entry_quest', $entry->id, $quest->id);

        $this->push([$change])->assertOk();
        $this->push([$change])
            ->assertOk()
            ->assertJsonCount(1, 'confirmed');

        $this->assertDatabaseCount('entry_quests', 1);
    }

    public function test_j2_delete_nonexistent_junction_is_noop_with_tombstone(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $quest = Quest::factory()->for($this->user)->create();

        $this->push([$this->junctionChange('entry_quest', $entry->id, $quest->id, 'delete')])
            ->assertOk()
            ->assertJsonCount(1, 'confirmed');

        $this->assertDatabaseCount('entry_quests', 0);
        $this->assertDatabaseHas('entry_quest_tombstones', [
            'user_id' => $this->user->id,
            'entry_id' => $entry->id,
            'quest_id' => $quest->id,
        ]);
    }

    public function test_j3_create_then_delete_in_same_request(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $quest = Quest::factory()->for($this->user)->create();

        $this->push([
            $this->junctionChange('entry_quest', $entry->id, $quest->id, 'create'),
            $this->junctionChange('entry_quest', $entry->id, $quest->id, 'delete'),
        ])
            ->assertOk()
            ->assertJsonCount(2, 'confirmed');

        $this->assertDatabaseCount('entry_quests', 0);
        $this->assertDatabaseHas('entry_quest_tombstones', [
            'entry_id' => $entry->id,
            'quest_id' => $quest->id,
        ]);
    }

    public function test_junction_create_clears_existing_tombstone(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $quest = Quest::factory()->for($this->user)->create();

        // create then delete → tombstone exists
        $this->push([$this->junctionChange('entry_quest', $entry->id, $quest->id, 'create')])->assertOk();
        $this->push([$this->junctionChange('entry_quest', $entry->id, $quest->id, 'delete')])->assertOk();
        $this->assertDatabaseCount('entry_quest_tombstones', 1);

        // re-create → tombstone removed, row restored
        $this->push([$this->junctionChange('entry_quest', $entry->id, $quest->id, 'create')])->assertOk();
        $this->assertDatabaseCount('entry_quests', 1);
        $this->assertDatabaseCount('entry_quest_tombstones', 0);
    }

    public function test_b1_push_attachment_metadata_stores_empty_uri_and_null_remote_uri(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $id = (string) Str::uuid();

        $this->push([$this->attachmentChange($id, $entry->id)])
            ->assertOk()
            ->assertJsonCount(1, 'confirmed');

        $att = EntryAttachment::query()->find($id);
        $this->assertSame('', $att->uri);
        $this->assertNull($att->remote_uri);
        $this->assertSame(800, $att->width);
        $this->assertSame(600, $att->height);
    }

    public function test_b2_push_attachment_with_non_empty_uri_is_still_stored_empty(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $id = (string) Str::uuid();

        $this->push([$this->attachmentChange($id, $entry->id, ['uri' => 'file:///var/mobile/path/abc.jpg'])])
            ->assertOk();

        $this->assertSame('', EntryAttachment::query()->find($id)->uri);
    }

    public function test_audio_metadata_stores_waveform_and_strips_uri(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $id = (string) Str::uuid();
        $waveform = [0.1, 0.5, 0.3, 0.8];

        $this->push([[
            'entityType' => 'entry_audio',
            'entityId' => $id,
            'operation' => 'create',
            'data' => [
                'id' => $id,
                'entryId' => $entry->id,
                'uri' => 'file:///audio.m4a',
                'remoteUri' => null,
                'durationMs' => 12500,
                'waveform' => $waveform,
                'isDeleted' => false,
                'createdAt' => '2026-05-13T10:00:00.000Z',
                'updatedAt' => '2026-05-13T10:00:00.000Z',
                'syncedAt' => null,
            ],
        ]])->assertOk()->assertJsonCount(1, 'confirmed');

        $audio = EntryAudio::query()->find($id);
        $this->assertSame('', $audio->uri);
        $this->assertSame(12500, $audio->duration_ms);
        $this->assertSame($waveform, $audio->waveform);
    }

    public function test_cross_user_isolation_silently_skips_foreign_entity(): void
    {
        $userA = User::factory()->create();
        $entryA = Entry::factory()->for($userA)->create(['title' => 'A original']);

        $this->push([
            $this->entryChange($entryA->id, [
                'title' => 'B attempted overwrite',
                'updatedAt' => '2030-01-01T00:00:00.000Z', // strictly newer
            ]),
        ])
            ->assertOk()
            ->assertExactJson(['confirmed' => [], 'conflicts' => []]);

        $entryA->refresh();
        $this->assertSame('A original', $entryA->title);
    }

    public function test_push_preserves_client_updated_at_round_trip(): void
    {
        $id = (string) Str::uuid();
        $clientUpdatedAt = '2026-05-13T10:30:45.123Z';

        $this->push([$this->entryChange($id, ['updatedAt' => $clientUpdatedAt])])->assertOk();

        $entry = Entry::query()->find($id);
        $this->assertSame($clientUpdatedAt, $entry->updated_at->utc()->format('Y-m-d\TH:i:s.v\Z'));
    }

    public function test_unauthenticated_push_returns_401(): void
    {
        $this->postJson('/api/sync/push', [
            'deviceId' => (string) Str::uuid(),
            'changes' => [],
        ])->assertStatus(401);
    }

    public function test_entry_character_junction_works_symmetrically(): void
    {
        $entry = Entry::factory()->for($this->user)->create();
        $character = Character::factory()->for($this->user)->create();

        $this->push([$this->junctionChange('entry_character', $entry->id, $character->id)])->assertOk();
        $this->assertDatabaseCount('entry_characters', 1);

        $this->push([$this->junctionChange('entry_character', $entry->id, $character->id, 'delete')])->assertOk();
        $this->assertDatabaseCount('entry_characters', 0);
        $this->assertDatabaseHas('entry_character_tombstones', [
            'entry_id' => $entry->id,
            'character_id' => $character->id,
        ]);
    }

    public function test_junction_with_foreign_owned_entry_is_skipped(): void
    {
        $otherUser = User::factory()->create();
        $foreignEntry = Entry::factory()->for($otherUser)->create();
        $myQuest = Quest::factory()->for($this->user)->create();

        $this->push([$this->junctionChange('entry_quest', $foreignEntry->id, $myQuest->id)])
            ->assertOk()
            ->assertExactJson(['confirmed' => [], 'conflicts' => []]);

        $this->assertDatabaseCount('entry_quests', 0);
    }

    public function test_q1_push_single_quote_to_empty_server(): void
    {
        $id = (string) Str::uuid();

        $this->push([$this->quoteChange($id, ['text' => 'Hello world', 'source' => 'TikTok'])])
            ->assertOk()
            ->assertExactJson(['confirmed' => [$id], 'conflicts' => []]);

        $quote = Quote::query()->find($id);
        $this->assertSame($this->user->id, $quote->user_id);
        $this->assertSame('Hello world', $quote->text);
        $this->assertSame('TikTok', $quote->source);
    }

    public function test_q1_push_quote_with_null_source(): void
    {
        $id = (string) Str::uuid();

        $this->push([$this->quoteChange($id, ['source' => null])])
            ->assertOk()
            ->assertJsonCount(1, 'confirmed');

        $this->assertNull(Quote::query()->find($id)->source);
    }

    public function test_q2_push_same_quote_twice_is_idempotent(): void
    {
        $id = (string) Str::uuid();
        $change = $this->quoteChange($id);

        $this->push([$change])->assertOk();
        $this->push([$change])
            ->assertOk()
            ->assertJsonCount(1, 'confirmed')
            ->assertJsonCount(0, 'conflicts');

        $this->assertDatabaseCount('quotes', 1);
    }

    public function test_q3_push_quote_with_older_updated_at_returns_conflict(): void
    {
        $id = (string) Str::uuid();

        $this->push([$this->quoteChange($id, ['text' => 'server v', 'updatedAt' => '2026-05-13T10:00:00.000Z'])])
            ->assertOk();

        $this->push([$this->quoteChange($id, ['text' => 'older client v', 'updatedAt' => '2026-05-13T09:00:00.000Z'])])
            ->assertOk()
            ->assertJsonCount(0, 'confirmed')
            ->assertJsonCount(1, 'conflicts')
            ->assertJsonPath('conflicts.0.entityType', 'quote')
            ->assertJsonPath('conflicts.0.entityId', $id)
            ->assertJsonPath('conflicts.0.serverVersion.text', 'server v');

        $this->assertSame('server v', Quote::query()->find($id)->text);
    }

    public function test_q4_soft_delete_quote_marks_is_deleted(): void
    {
        $id = (string) Str::uuid();
        $this->push([$this->quoteChange($id)])->assertOk();

        $this->push([
            $this->quoteChange($id, ['isDeleted' => true, 'updatedAt' => '2026-05-13T11:00:00.000Z'], 'delete'),
        ])->assertOk()->assertJsonCount(1, 'confirmed');

        $this->assertTrue(Quote::query()->find($id)->is_deleted);
    }

    public function test_q6_cross_user_isolation_silently_skips_foreign_quote(): void
    {
        $userA = User::factory()->create();
        $quoteA = Quote::factory()->for($userA)->create(['text' => 'A original']);

        $this->push([
            $this->quoteChange($quoteA->id, [
                'text' => 'B attempted overwrite',
                'updatedAt' => '2030-01-01T00:00:00.000Z', // strictly newer
            ]),
        ])
            ->assertOk()
            ->assertExactJson(['confirmed' => [], 'conflicts' => []]);

        $quoteA->refresh();
        $this->assertSame('A original', $quoteA->text);
    }
}
