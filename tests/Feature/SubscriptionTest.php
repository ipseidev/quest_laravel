<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_account_has_no_active_subscription(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasActiveSubscription());
    }

    public function test_active_annual_subscription_is_recognized(): void
    {
        $user = User::factory()->subscribed()->create();

        $this->assertTrue($user->hasActiveSubscription());
    }

    public function test_lapsed_subscription_is_inactive(): void
    {
        $user = User::factory()->create([
            'subscription_product_id' => 'annual',
            'subscription_expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($user->hasActiveSubscription());
    }

    public function test_lifetime_entitlement_never_expires(): void
    {
        $user = User::factory()->subscribed('lifetime')->create();

        $this->assertNull($user->subscription_expires_at);
        $this->assertTrue($user->hasActiveSubscription());
    }

    public function test_ai_access_requires_both_subscription_and_consent(): void
    {
        // Subscribed but not opted in → no AI.
        $subscribedOnly = User::factory()->subscribed()->create();
        $this->assertFalse($subscribedOnly->hasAiAccess());

        // Opted in but free account (no subscription) → no AI.
        $consentOnly = User::factory()->optedIntoAi()->create();
        $this->assertFalse($consentOnly->hasAiAccess());

        // Both → AI access.
        $entitled = User::factory()->subscribed()->optedIntoAi()->create();
        $this->assertTrue($entitled->hasAiAccess());
    }
}
