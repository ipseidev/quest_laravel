<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['quest.rate_limits.sync' => 3]);
        RateLimiter::clear('sync');
    }

    public function test_i3_sync_push_returns_429_after_limit_is_reached(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;
        $payload = ['deviceId' => (string) Str::uuid(), 'changes' => []];

        for ($i = 1; $i <= 3; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/sync/push', $payload)
                ->assertOk();
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', $payload);

        $response->assertStatus(429)
            ->assertJsonPath('error', 'rate_limited')
            ->assertHeader('Retry-After', '60');
    }

    public function test_rate_limit_is_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tokenA = $userA->createToken('mobile')->plainTextToken;
        $tokenB = $userB->createToken('mobile')->plainTextToken;
        $payload = ['deviceId' => (string) Str::uuid(), 'changes' => []];

        // User A exhausts limit.
        for ($i = 1; $i <= 3; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$tokenA)
                ->postJson('/api/sync/push', $payload)
                ->assertOk();
        }
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/sync/push', $payload)
            ->assertStatus(429);

        // User B unaffected.
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->postJson('/api/sync/push', $payload)
            ->assertOk();
    }
}
