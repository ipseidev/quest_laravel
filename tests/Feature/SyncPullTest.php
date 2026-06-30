<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\EntryAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SyncPullTest extends TestCase
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

    private function pull(?string $lastPullTimestamp = null, ?string $token = null): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.($token ?? $this->token))
            ->postJson('/api/sync/pull', [
                'deviceId' => (string) Str::uuid(),
                'lastPullTimestamp' => $lastPullTimestamp,
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

    private function questChange(string $id, array $overrides = []): array
    {
        return [
            'entityType' => 'quest',
            'entityId' => $id,
            'operation' => 'create',
            'data' => array_merge([
                'id' => $id,
                'type' => 'main',
                'title' => 'Quest title',
                'description' => 'Quest desc',
                'status' => 'active',
                'color' => '#7B6BD4',
                'icon' => null,
                'startedAt' => null,
                'completedAt' => null,
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

    public function test_pull_with_empty_server_returns_no_changes(): void
    {
        $this->pull()
            ->assertOk()
            ->assertJsonCount(0, 'changes')
            ->assertJsonStructure(['changes', 'serverTimestamp']);
    }

    public function test_s2_pull_after_push_with_recent_cursor_returns_no_changes(): void
    {
        $id = (string) Str::uuid();
        $this->push([$this->entryChange($id)])->assertOk();

        $firstPull = $this->pull()->assertOk();
        $serverTs = $firstPull->json('serverTimestamp');
        $this->assertCount(1, $firstPull->json('changes'));

        $this->pull($serverTs)
            ->assertOk()
            ->assertJsonCount(0, 'changes');
    }

    public function test_s3_push_then_pull_returns_the_entry(): void
    {
        $id = (string) Str::uuid();
        $this->push([$this->entryChange($id, ['title' => 'Pull me'])])->assertOk();

        $response = $this->pull();
        $response->assertOk()
            ->assertJsonCount(1, 'changes')
            ->assertJsonPath('changes.0.entityType', 'entry')
            ->assertJsonPath('changes.0.operation', 'upsert')
            ->assertJsonPath('changes.0.data.id', $id)
            ->assertJsonPath('changes.0.data.title', 'Pull me');
    }

    public function test_e2_pull_returns_plaintext_for_encrypted_fields(): void
    {
        $id = (string) Str::uuid();
        $this->push([$this->entryChange($id, [
            'title' => 'Secret thoughts',
            'html' => '<p>Sensitive content</p>',
        ])])->assertOk();

        $changes = $this->pull()->json('changes');

        $this->assertSame('Secret thoughts', $changes[0]['data']['title']);
        $this->assertSame('<p>Sensitive content</p>', $changes[0]['data']['html']);
    }

    public function test_j4_pull_returns_entry_quest_and_junction(): void
    {
        $entryId = (string) Str::uuid();
        $questId = (string) Str::uuid();

        $this->push([
            $this->entryChange($entryId),
            $this->questChange($questId),
            $this->junctionChange('entry_quest', $entryId, $questId),
        ])->assertOk();

        $changes = $this->pull()->assertOk()->json('changes');

        $types = array_column($changes, 'entityType');
        $this->assertSame(['quest', 'entry', 'entry_quest'], $types);

        $junction = end($changes);
        $this->assertSame('upsert', $junction['operation']);
        $this->assertSame($entryId, $junction['data']['entryId']);
        $this->assertSame($questId, $junction['data']['questId']);
    }

    public function test_j5_unlink_junction_is_emitted_as_delete_in_pull(): void
    {
        $entryId = (string) Str::uuid();
        $questId = (string) Str::uuid();

        $this->push([
            $this->entryChange($entryId),
            $this->questChange($questId),
            $this->junctionChange('entry_quest', $entryId, $questId),
        ])->assertOk();

        $firstPull = $this->pull();
        $cursor = $firstPull->json('serverTimestamp');

        $this->push([$this->junctionChange('entry_quest', $entryId, $questId, 'delete')])->assertOk();

        $secondPull = $this->pull($cursor)->assertOk();
        $changes = $secondPull->json('changes');

        $junctionDeletes = array_filter($changes, fn ($c) => $c['entityType'] === 'entry_quest' && $c['operation'] === 'delete');
        $this->assertCount(1, $junctionDeletes);

        $delete = array_values($junctionDeletes)[0];
        $this->assertSame($entryId, $delete['data']['entryId']);
        $this->assertSame($questId, $delete['data']['questId']);
    }

    public function test_b7_pull_emits_attachment_with_remote_uri(): void
    {
        $entryId = (string) Str::uuid();
        $attId = (string) Str::uuid();
        $this->push([
            $this->entryChange($entryId),
            $this->attachmentChange($attId, $entryId),
        ])->assertOk();

        // Simulate upload endpoint having set remote_uri (real impl in Lot 7).
        EntryAttachment::query()->where('id', $attId)
            ->update(['remote_uri' => 'https://cdn.example.com/attachments/'.$attId.'.jpg']);

        $changes = $this->pull()->json('changes');
        $att = collect($changes)->firstWhere('entityType', 'entry_attachment');

        $this->assertNotNull($att);
        $this->assertSame('', $att['data']['uri']);
        $this->assertStringStartsWith('https://cdn.example.com/', $att['data']['remoteUri']);
    }

    public function test_b8_soft_deleted_attachment_pulled_with_is_deleted_true(): void
    {
        $entryId = (string) Str::uuid();
        $attId = (string) Str::uuid();

        $this->push([
            $this->entryChange($entryId),
            $this->attachmentChange($attId, $entryId),
        ])->assertOk();

        $this->push([
            $this->attachmentChange($attId, $entryId, ['isDeleted' => true, 'updatedAt' => '2026-05-13T11:00:00.000Z'], 'delete'),
        ])->assertOk();

        $changes = $this->pull()->json('changes');
        $att = collect($changes)->firstWhere('entityType', 'entry_attachment');

        $this->assertSame('upsert', $att['operation']);
        $this->assertTrue($att['data']['isDeleted']);
    }

    public function test_soft_deleted_content_is_emitted_as_upsert_never_as_delete(): void
    {
        $id = (string) Str::uuid();
        $this->push([$this->entryChange($id)])->assertOk();
        $this->push([$this->entryChange($id, ['isDeleted' => true, 'updatedAt' => '2026-05-13T11:00:00.000Z'], 'delete')])->assertOk();

        $changes = $this->pull()->json('changes');
        $entryChanges = array_filter($changes, fn ($c) => $c['entityType'] === 'entry');

        $this->assertCount(1, $entryChanges);
        $entry = array_values($entryChanges)[0];
        $this->assertSame('upsert', $entry['operation']);
        $this->assertTrue($entry['data']['isDeleted']);
    }

    public function test_pull_order_respects_fk_dependencies(): void
    {
        $questId = (string) Str::uuid();
        $entryId = (string) Str::uuid();
        $charId = (string) Str::uuid();
        $attId = (string) Str::uuid();

        $this->push([
            $this->entryChange($entryId),
            $this->questChange($questId),
            $this->attachmentChange($attId, $entryId),
            [
                'entityType' => 'character',
                'entityId' => $charId,
                'operation' => 'create',
                'data' => [
                    'id' => $charId,
                    'name' => 'Alice',
                    'relationship' => null,
                    'note' => '',
                    'photoUri' => '',
                    'remotePhotoUri' => null,
                    'color' => null,
                    'isDeleted' => false,
                    'createdAt' => '2026-05-13T10:00:00.000Z',
                    'updatedAt' => '2026-05-13T10:00:00.000Z',
                    'syncedAt' => null,
                ],
            ],
            $this->junctionChange('entry_quest', $entryId, $questId),
            $this->junctionChange('entry_character', $entryId, $charId),
        ])->assertOk();

        $changes = $this->pull()->json('changes');
        $types = array_column($changes, 'entityType');

        $questIdx = array_search('quest', $types, true);
        $charIdx = array_search('character', $types, true);
        $entryIdx = array_search('entry', $types, true);
        $attIdx = array_search('entry_attachment', $types, true);
        $eqIdx = array_search('entry_quest', $types, true);
        $ecIdx = array_search('entry_character', $types, true);

        $this->assertLessThan($entryIdx, $questIdx);
        $this->assertLessThan($entryIdx, $charIdx);
        $this->assertLessThan($attIdx, $entryIdx);
        $this->assertLessThan($eqIdx, $entryIdx);
        $this->assertLessThan($ecIdx, $entryIdx);
    }

    public function test_pull_cursor_filters_already_seen_rows(): void
    {
        $idA = (string) Str::uuid();
        $idB = (string) Str::uuid();

        $this->push([$this->entryChange($idA, ['title' => 'A'])])->assertOk();

        $cursor = $this->pull()->json('serverTimestamp');

        // updatedAt must be strictly after the cursor (= server now() at first pull).
        $futureUpdatedAt = now()->addHour()->format('Y-m-d\TH:i:s.v\Z');
        $this->push([$this->entryChange($idB, ['title' => 'B', 'updatedAt' => $futureUpdatedAt])])->assertOk();

        $changes = $this->pull($cursor)->json('changes');
        $this->assertCount(1, $changes);
        $this->assertSame($idB, $changes[0]['data']['id']);
    }

    public function test_l1_cross_device_lww_roundtrip(): void
    {
        $id = (string) Str::uuid();

        // Device A pushes v1.
        $this->push([$this->entryChange($id, ['title' => 'v1'])])->assertOk();

        // Device B pulls and stores its cursor.
        $cursorB = $this->pull()->json('serverTimestamp');
        $this->pull()->assertJsonPath('changes.0.data.title', 'v1');

        // Device B updates with a timestamp strictly after the cursor.
        $newUpdatedAt = now()->addHour()->format('Y-m-d\TH:i:s.v\Z');
        $this->push([$this->entryChange($id, ['title' => 'v2', 'updatedAt' => $newUpdatedAt])])->assertOk();

        // Device A pulls with its prior cursor and receives B's version.
        $changes = $this->pull($cursorB)->json('changes');
        $entryChange = collect($changes)->firstWhere('entityType', 'entry');
        $this->assertSame('v2', $entryChange['data']['title']);
        $this->assertSame($newUpdatedAt, $entryChange['data']['updatedAt']);
    }

    public function test_pull_only_returns_authenticated_users_data(): void
    {
        $otherUser = User::factory()->create();
        $otherEntry = Entry::factory()->for($otherUser)->create(['title' => 'foreign entry']);

        $changes = $this->pull()->json('changes');

        $this->assertCount(0, $changes);
        $this->assertDatabaseHas('entries', ['id' => $otherEntry->id]); // still exists, just not visible
    }

    public function test_unauthenticated_pull_returns_401(): void
    {
        $this->postJson('/api/sync/pull', [
            'deviceId' => (string) Str::uuid(),
            'lastPullTimestamp' => null,
        ])->assertStatus(401);
    }

    public function test_quote_round_trips_through_pull(): void
    {
        $id = (string) Str::uuid();

        $this->push([[
            'entityType' => 'quote',
            'entityId' => $id,
            'operation' => 'create',
            'data' => [
                'id' => $id,
                'text' => 'Pull me',
                'source' => 'A book',
                'note' => 'note',
                'isDeleted' => false,
                'createdAt' => '2026-05-13T10:00:00.000Z',
                'updatedAt' => '2026-05-13T10:00:00.000Z',
                'syncedAt' => null,
            ],
        ]])->assertOk();

        $changes = $this->pull()->assertOk()->json('changes');
        $quote = collect($changes)->firstWhere('entityType', 'quote');

        $this->assertNotNull($quote);
        $this->assertSame('upsert', $quote['operation']);
        $this->assertSame($id, $quote['data']['id']);
        $this->assertSame('Pull me', $quote['data']['text']);
        $this->assertSame('A book', $quote['data']['source']);
        $this->assertSame('note', $quote['data']['note']);
        $this->assertNull($quote['data']['syncedAt']);
    }

    public function test_quote_is_emitted_before_entries_in_pull_order(): void
    {
        $quoteId = (string) Str::uuid();
        $entryId = (string) Str::uuid();

        $this->push([
            $this->entryChange($entryId),
            [
                'entityType' => 'quote',
                'entityId' => $quoteId,
                'operation' => 'create',
                'data' => [
                    'id' => $quoteId,
                    'text' => 'Q',
                    'source' => null,
                    'note' => '',
                    'isDeleted' => false,
                    'createdAt' => '2026-05-13T10:00:00.000Z',
                    'updatedAt' => '2026-05-13T10:00:00.000Z',
                    'syncedAt' => null,
                ],
            ],
        ])->assertOk();

        $types = array_column($this->pull()->json('changes'), 'entityType');
        $this->assertLessThan(
            array_search('entry', $types, true),
            array_search('quote', $types, true),
        );
    }
}
