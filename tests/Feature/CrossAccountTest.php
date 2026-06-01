<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrossAccountTest extends TestCase
{
    use RefreshDatabase;

    private function entryPayload(string $id, array $overrides = []): array
    {
        return [
            'entityType' => 'entry',
            'entityId' => $id,
            'operation' => 'create',
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

    public function test_m1_new_user_register_then_push_local_entries(): void
    {
        $reg = $this->postJson('/api/auth/password/register', [
            'email' => 'm1@test.io',
            'password' => 'password123',
            'deviceId' => (string) Str::uuid(),
        ]);
        $reg->assertOk();
        $token = $reg->json('token');
        $userId = $reg->json('user.id');

        $changes = [];
        for ($i = 0; $i < 10; $i++) {
            $changes[] = $this->entryPayload((string) Str::uuid());
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', ['deviceId' => (string) Str::uuid(), 'changes' => $changes])
            ->assertOk()
            ->assertJsonCount(10, 'confirmed')
            ->assertJsonCount(0, 'conflicts');

        $this->assertSame(10, Entry::query()->withoutGlobalScopes()->where('user_id', $userId)->count());
    }

    public function test_m2_logout_login_same_user_pull_with_cursor_is_noop(): void
    {
        $register = $this->postJson('/api/auth/password/register', [
            'email' => 'm2@test.io',
            'password' => 'password123',
            'deviceId' => (string) Str::uuid(),
        ])->assertOk();
        $token1 = $register->json('token');

        // Push and pull as initial sync.
        $entryId = (string) Str::uuid();
        $this->withHeader('Authorization', 'Bearer '.$token1)
            ->postJson('/api/sync/push', ['deviceId' => (string) Str::uuid(), 'changes' => [$this->entryPayload($entryId)]])
            ->assertOk();

        $cursor = $this->withHeader('Authorization', 'Bearer '.$token1)
            ->postJson('/api/sync/pull', ['deviceId' => (string) Str::uuid(), 'lastPullTimestamp' => null])
            ->json('serverTimestamp');

        // Logout (revokes token1).
        $this->withHeader('Authorization', 'Bearer '.$token1)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        // Login again (new token).
        $login = $this->postJson('/api/auth/password/login', [
            'email' => 'm2@test.io',
            'password' => 'password123',
            'deviceId' => (string) Str::uuid(),
        ])->assertOk();
        $token2 = $login->json('token');

        // Pull with stored cursor → no changes.
        $this->withHeader('Authorization', 'Bearer '.$token2)
            ->postJson('/api/sync/pull', ['deviceId' => (string) Str::uuid(), 'lastPullTimestamp' => $cursor])
            ->assertOk()
            ->assertJsonCount(0, 'changes');
    }

    public function test_m3_different_user_pushes_local_data_attributed_to_new_account(): void
    {
        // User A: register, push, logout.
        $regA = $this->postJson('/api/auth/password/register', [
            'email' => 'a@test.io',
            'password' => 'password123',
            'deviceId' => (string) Str::uuid(),
        ])->assertOk();
        $tokenA = $regA->json('token');
        $userAId = $regA->json('user.id');

        $aEntryId = (string) Str::uuid();
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/sync/push', ['deviceId' => (string) Str::uuid(), 'changes' => [$this->entryPayload($aEntryId, ['title' => "A's entry"])]])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        // User B: register on same device, push merged data with fresh UUIDs.
        $regB = $this->postJson('/api/auth/password/register', [
            'email' => 'b@test.io',
            'password' => 'password123',
            'deviceId' => (string) Str::uuid(),
        ])->assertOk();
        $tokenB = $regB->json('token');
        $userBId = $regB->json('user.id');

        $bEntryId = (string) Str::uuid();
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->postJson('/api/sync/push', ['deviceId' => (string) Str::uuid(), 'changes' => [$this->entryPayload($bEntryId, ['title' => 'merged entry'])]])
            ->assertOk()
            ->assertJsonCount(1, 'confirmed');

        // A's data still belongs to A, B's data belongs to B.
        $this->assertSame($userAId, Entry::query()->withoutGlobalScopes()->find($aEntryId)?->user_id);
        $this->assertSame($userBId, Entry::query()->withoutGlobalScopes()->find($bEntryId)?->user_id);
    }

    public function test_i1_repeated_push_yields_same_state(): void
    {
        $reg = $this->postJson('/api/auth/password/register', [
            'email' => 'i1@test.io',
            'password' => 'password123',
            'deviceId' => (string) Str::uuid(),
        ])->assertOk();
        $token = $reg->json('token');
        $userId = $reg->json('user.id');

        $changes = [];
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $id = (string) Str::uuid();
            $ids[] = $id;
            $changes[] = $this->entryPayload($id);
        }

        $payload = ['deviceId' => (string) Str::uuid(), 'changes' => $changes];

        // First push.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', $payload)
            ->assertOk()
            ->assertJsonCount(5, 'confirmed');

        // Replay — identical end state, no duplicates.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', $payload)
            ->assertOk()
            ->assertJsonCount(5, 'confirmed');

        $this->assertSame(5, Entry::query()->withoutGlobalScopes()->where('user_id', $userId)->count());
    }
}
