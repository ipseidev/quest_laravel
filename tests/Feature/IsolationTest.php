<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\EntryAttachment;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_scope_filters_foreign_entries_when_authed(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $entryA = Entry::factory()->for($userA)->create();
        $entryB = Entry::factory()->for($userB)->create();

        $this->actingAs($userB);

        $this->assertNull(Entry::query()->find($entryA->id));
        $this->assertNotNull(Entry::query()->find($entryB->id));
        $this->assertSame(1, Entry::query()->count());
    }

    public function test_global_scope_filters_foreign_attachments(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $entryA = Entry::factory()->for($userA)->create();
        $entryB = Entry::factory()->for($userB)->create();

        $attA = EntryAttachment::factory()->for($entryA)->create();
        $attB = EntryAttachment::factory()->for($entryB)->create();

        $this->actingAs($userB);

        $this->assertNull(EntryAttachment::query()->find($attA->id));
        $this->assertNotNull(EntryAttachment::query()->find($attB->id));
    }

    public function test_scope_inactive_without_auth_context(): void
    {
        $user = User::factory()->create();
        $entry = Entry::factory()->for($user)->create();

        // No actingAs — Auth::check() is false; retention jobs and console commands run here.
        $this->assertNotNull(Entry::query()->find($entry->id));
    }

    public function test_x2_get_me_returns_only_authenticated_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tokenB = $userB->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $userB->id)
            ->assertJsonPath('user.email', $userB->email);
    }

    public function test_x4_account_deletion_isolated_from_other_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $entryB = Entry::factory()->for($userB)->create();
        $questB = Quest::factory()->for($userB)->create();

        $tokenA = $userA->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->deleteJson('/api/me')
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $userA->id]);
        $this->assertDatabaseHas('users', ['id' => $userB->id]);
        $this->assertDatabaseHas('entries', ['id' => $entryB->id]);
        $this->assertDatabaseHas('quests', ['id' => $questB->id]);
    }

    public function test_l3_b_pushes_newer_then_a_pulls_and_receives_b_version(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;
        $entryId = (string) Str::uuid();

        $entryPayload = fn (string $title, string $updatedAt) => [
            'entityType' => 'entry',
            'entityId' => $entryId,
            'operation' => 'create',
            'data' => [
                'id' => $entryId,
                'title' => $title,
                'html' => '<p>body</p>',
                'mood' => null,
                'latitude' => null,
                'longitude' => null,
                'entryDate' => null,
                'isDeleted' => false,
                'createdAt' => now()->subHour()->format('Y-m-d\TH:i:s.v\Z'),
                'updatedAt' => $updatedAt,
                'syncedAt' => null,
            ],
        ];

        // Device A pushes v1.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'deviceId' => (string) Str::uuid(),
                'changes' => [$entryPayload('v1 from A', now()->subHour()->format('Y-m-d\TH:i:s.v\Z'))],
            ])->assertOk();

        // Device A pulls (records cursor).
        $cursorA = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/pull', ['deviceId' => (string) Str::uuid(), 'lastPullTimestamp' => null])
            ->json('serverTimestamp');

        // Device B pushes v2 with updatedAt strictly after cursorA.
        $v2UpdatedAt = now()->addHour()->format('Y-m-d\TH:i:s.v\Z');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'deviceId' => (string) Str::uuid(),
                'changes' => [$entryPayload('v2 from B', $v2UpdatedAt)],
            ])->assertOk();

        // Device A pulls with its prior cursor → receives B's version.
        $changes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/pull', ['deviceId' => (string) Str::uuid(), 'lastPullTimestamp' => $cursorA])
            ->json('changes');

        $entryChange = collect($changes)->firstWhere('entityType', 'entry');
        $this->assertNotNull($entryChange);
        $this->assertSame('v2 from B', $entryChange['data']['title']);
        $this->assertSame($v2UpdatedAt, $entryChange['data']['updatedAt']);
    }

    public function test_token_cannot_authenticate_after_other_user_deletion(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tokenA = $userA->createToken('mobile')->plainTextToken;
        $tokenB = $userB->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->deleteJson('/api/me')
            ->assertNoContent();

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/me')
            ->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->getJson('/api/me')
            ->assertOk();
    }
}
