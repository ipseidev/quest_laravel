<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The RevenueCat webhook — the only thing that grants an account Nacre Plus.
 *
 * Worth testing exhaustively because every failure mode is silent and costs money in one
 * direction or the other: a missed grant leaves a paying customer on the free tier
 * (capped media, no monthly Chapter), and a missed revocation keeps serving someone who
 * refunded. Neither surfaces as an error anywhere.
 */
class RevenueCatWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_value';

    private const ENDPOINT = '/api/webhooks/revenuecat';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.revenuecat.webhook_secret' => self::SECRET]);
    }

    /**
     * A payload shaped like RevenueCat's, with only the fields this server reads.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function event(string $type, array $overrides = []): array
    {
        return array_merge([
            'type' => $type,
            'app_user_id' => (string) Str::uuid(),
            'product_id' => 'nacre_plus_annual',
            'entitlement_ids' => ['plus'],
            'expiration_at_ms' => now()->addYear()->getTimestampMs(),
            'event_timestamp_ms' => now()->getTimestampMs(),
        ], $overrides);
    }

    /** @param  array<string, mixed>  $event */
    private function send(array $event, ?string $authorization = 'Bearer '.self::SECRET): TestResponse
    {
        $headers = $authorization === null ? [] : ['Authorization' => $authorization];

        return $this->postJson(self::ENDPOINT, ['api_version' => '1.0', 'event' => $event], $headers);
    }

    // --- Authorisation ---

    public function test_it_rejects_a_request_without_the_shared_secret(): void
    {
        $user = User::factory()->create();

        $this->send($this->event('INITIAL_PURCHASE', ['app_user_id' => $user->id]), authorization: null)
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');

        $this->assertNull($user->fresh()->subscription_product_id);
    }

    public function test_it_rejects_a_wrong_secret(): void
    {
        $user = User::factory()->create();

        $this->send($this->event('INITIAL_PURCHASE', ['app_user_id' => $user->id]), 'Bearer nope')
            ->assertStatus(401);

        $this->assertNull($user->fresh()->subscription_product_id);
    }

    /**
     * An unconfigured secret must close the endpoint, not open it. Otherwise a fresh
     * deployment that forgot the env var would let anyone grant themselves Plus with one
     * POST.
     */
    public function test_it_rejects_everything_when_no_secret_is_configured(): void
    {
        config(['services.revenuecat.webhook_secret' => null]);
        $user = User::factory()->create();

        $this->send($this->event('INITIAL_PURCHASE', ['app_user_id' => $user->id]))
            ->assertStatus(401);

        $this->assertNull($user->fresh()->subscription_product_id);
    }

    public function test_it_accepts_the_secret_with_or_without_a_bearer_prefix(): void
    {
        foreach ([self::SECRET, 'Bearer '.self::SECRET] as $header) {
            $user = User::factory()->create();

            $this->send($this->event('INITIAL_PURCHASE', ['app_user_id' => $user->id]), $header)
                ->assertOk()
                ->assertJsonPath('status', 'granted');

            $this->assertTrue($user->fresh()->hasActiveSubscription());
        }
    }

    public function test_it_rejects_a_body_with_no_event(): void
    {
        $this->postJson(self::ENDPOINT, ['api_version' => '1.0'], ['Authorization' => 'Bearer '.self::SECRET])
            ->assertStatus(400)
            ->assertJsonPath('error', 'bad_request');
    }

    // --- Granting ---

    public function test_an_initial_purchase_grants_the_entitlement(): void
    {
        $user = User::factory()->create();
        $expiresAt = now()->addYear()->startOfSecond();

        $this->send($this->event('INITIAL_PURCHASE', [
            'app_user_id' => $user->id,
            'expiration_at_ms' => $expiresAt->getTimestampMs(),
        ]))->assertOk()->assertJsonPath('status', 'granted');

        $user->refresh();

        $this->assertSame('nacre_plus_annual', $user->subscription_product_id);
        $this->assertTrue($expiresAt->equalTo($user->subscription_expires_at));
        $this->assertTrue($user->hasActiveSubscription());
    }

    public function test_a_renewal_pushes_the_expiry_out(): void
    {
        $user = User::factory()->subscribed()->create();
        $newExpiry = now()->addYears(2)->startOfSecond();

        $this->send($this->event('RENEWAL', [
            'app_user_id' => $user->id,
            'expiration_at_ms' => $newExpiry->getTimestampMs(),
        ]))->assertOk();

        $this->assertTrue($newExpiry->equalTo($user->fresh()->subscription_expires_at));
    }

    /**
     * A subscription event with no expiry would be stored as a null expiry, which
     * `hasActiveSubscription()` reads as a lifetime entitlement. Refusing is the only
     * safe reading of a malformed payload.
     */
    public function test_a_subscription_grant_without_an_expiry_is_refused(): void
    {
        $user = User::factory()->create();

        $this->send($this->event('INITIAL_PURCHASE', [
            'app_user_id' => $user->id,
            'expiration_at_ms' => null,
        ]))->assertOk()->assertJsonPath('status', 'malformed');

        $this->assertFalse($user->fresh()->hasActiveSubscription());
    }

    public function test_a_non_renewing_purchase_without_an_expiry_is_a_lifetime_grant(): void
    {
        $user = User::factory()->create();

        $this->send($this->event('NON_RENEWING_PURCHASE', [
            'app_user_id' => $user->id,
            'product_id' => 'nacre_plus_lifetime',
            'expiration_at_ms' => null,
        ]))->assertOk()->assertJsonPath('status', 'granted');

        $user->refresh();

        $this->assertNull($user->subscription_expires_at);
        $this->assertTrue($user->hasActiveSubscription());
    }

    // --- Ending ---

    /**
     * Turning off auto-renew is not the same as losing access. Getting this wrong would
     * cut a subscriber off from a period they have already paid for.
     */
    public function test_cancelling_keeps_access_until_the_period_ends(): void
    {
        $user = User::factory()->subscribed()->create();
        $endOfPeriod = now()->addMonth()->startOfSecond();

        $this->send($this->event('CANCELLATION', [
            'app_user_id' => $user->id,
            'cancel_reason' => 'UNSUBSCRIBE',
            'expiration_at_ms' => $endOfPeriod->getTimestampMs(),
        ]))->assertOk()->assertJsonPath('status', 'settled');

        $user->refresh();

        $this->assertTrue($user->hasActiveSubscription());
        $this->assertTrue($endOfPeriod->equalTo($user->subscription_expires_at));
    }

    /**
     * A refund also arrives as CANCELLATION, and its expiration is still the end of the
     * refunded period — so honouring that date would keep serving someone who got their
     * money back.
     */
    public function test_a_refund_ends_access_immediately(): void
    {
        $user = User::factory()->subscribed()->create();

        $this->send($this->event('CANCELLATION', [
            'app_user_id' => $user->id,
            'cancel_reason' => 'CUSTOMER_SUPPORT',
            'expiration_at_ms' => now()->addMonths(11)->getTimestampMs(),
        ]))->assertOk();

        $this->assertFalse($user->fresh()->hasActiveSubscription());
    }

    public function test_an_expiration_ends_access(): void
    {
        $user = User::factory()->subscribed()->create();

        $this->send($this->event('EXPIRATION', [
            'app_user_id' => $user->id,
            'expiration_at_ms' => now()->subMinute()->getTimestampMs(),
        ]))->assertOk();

        $user->refresh();

        $this->assertFalse($user->hasActiveSubscription());
        // The product is kept on record: a lapsed subscriber is still someone who paid,
        // and the event's product id wins over whatever was stored before.
        $this->assertSame('nacre_plus_annual', $user->subscription_product_id);
    }

    /**
     * A failed payment opens a grace period during which RevenueCat still reports the
     * entitlement as active. Revoking here would cut off a subscriber whose card just
     * needs re-authorising.
     */
    public function test_a_billing_issue_changes_nothing(): void
    {
        $user = User::factory()->subscribed()->create();
        $before = $user->subscription_expires_at;

        $this->send($this->event('BILLING_ISSUE', ['app_user_id' => $user->id]))
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        $user->refresh();

        $this->assertTrue($user->hasActiveSubscription());
        $this->assertTrue($before->equalTo($user->subscription_expires_at));
    }

    // --- Delivery hazards ---

    /**
     * Webhooks are retried, so a RENEWAL can land after the EXPIRATION that followed it.
     * Applying it would resurrect a finished subscription.
     */
    public function test_an_event_older_than_the_last_applied_one_is_dropped(): void
    {
        $user = User::factory()->create();
        $now = now()->getTimestampMs();

        $this->send($this->event('EXPIRATION', [
            'app_user_id' => $user->id,
            'event_timestamp_ms' => $now,
            'expiration_at_ms' => now()->subDay()->getTimestampMs(),
        ]))->assertOk();

        $this->assertFalse($user->fresh()->hasActiveSubscription());

        // The same subscriber's earlier RENEWAL, delivered late.
        $this->send($this->event('RENEWAL', [
            'app_user_id' => $user->id,
            'event_timestamp_ms' => $now - 60_000,
            'expiration_at_ms' => now()->addYear()->getTimestampMs(),
        ]))->assertOk()->assertJsonPath('status', 'stale');

        $this->assertFalse($user->fresh()->hasActiveSubscription());
    }

    public function test_redelivering_the_same_event_is_idempotent(): void
    {
        $user = User::factory()->create();
        $event = $this->event('INITIAL_PURCHASE', ['app_user_id' => $user->id]);

        $this->send($event)->assertOk()->assertJsonPath('status', 'granted');
        $first = $user->fresh()->subscription_expires_at;

        $this->send($event)->assertOk()->assertJsonPath('status', 'granted');

        $this->assertTrue($first->equalTo($user->fresh()->subscription_expires_at));
    }

    /**
     * Buying before signing in is normal: the purchase sits under a RevenueCat anonymous
     * id that matches no account here. It has to be acknowledged (a non-2xx would have
     * RevenueCat retrying for hours) and it must not break the query — `users.id` is a
     * Postgres uuid column, so `$RCAnonymousID:…` would fail it outright if passed through.
     */
    public function test_an_anonymous_purchaser_is_acknowledged_without_error(): void
    {
        $this->send($this->event('INITIAL_PURCHASE', [
            'app_user_id' => '$RCAnonymousID:8f3a2b1c4d5e6f70',
            'original_app_user_id' => '$RCAnonymousID:8f3a2b1c4d5e6f70',
        ]))->assertOk()->assertJsonPath('status', 'unknown_subscriber');
    }

    public function test_it_finds_the_account_through_an_alias(): void
    {
        $user = User::factory()->create();

        $this->send($this->event('INITIAL_PURCHASE', [
            'app_user_id' => '$RCAnonymousID:8f3a2b1c4d5e6f70',
            'original_app_user_id' => '$RCAnonymousID:8f3a2b1c4d5e6f70',
            'aliases' => ['$RCAnonymousID:8f3a2b1c4d5e6f70', $user->id],
        ]))->assertOk()->assertJsonPath('status', 'granted');

        $this->assertTrue($user->fresh()->hasActiveSubscription());
    }

    /**
     * What fires when someone who bought anonymously then signs in, and when an
     * entitlement is restored onto a different account.
     */
    public function test_a_transfer_moves_the_entitlement_between_accounts(): void
    {
        $from = User::factory()->subscribed()->create();
        $to = User::factory()->create();

        $this->send($this->event('TRANSFER', [
            'app_user_id' => $to->id,
            'transferred_from' => [$from->id],
            'transferred_to' => [$to->id],
        ]))->assertOk()->assertJsonPath('status', 'transferred');

        $this->assertFalse($from->fresh()->hasActiveSubscription());
        $this->assertTrue($to->fresh()->hasActiveSubscription());
    }

    // --- Scoping ---

    public function test_an_event_for_another_entitlement_is_ignored(): void
    {
        $user = User::factory()->create();

        $this->send($this->event('INITIAL_PURCHASE', [
            'app_user_id' => $user->id,
            'entitlement_ids' => ['some_other_product'],
        ]))->assertOk()->assertJsonPath('status', 'other_entitlement');

        $this->assertNull($user->fresh()->subscription_product_id);
    }

    public function test_the_dashboard_test_event_touches_nothing(): void
    {
        $user = User::factory()->create();

        $this->send($this->event('TEST', ['app_user_id' => $user->id]))
            ->assertOk()
            ->assertJsonPath('status', 'test');

        $this->assertNull($user->fresh()->subscription_product_id);
    }

    // --- The two breakages this endpoint exists to fix ---

    /**
     * Before the webhook existed, `subscription_product_id` was never written, so a
     * paying subscriber was held to the free media quota and got 402 on upload.
     */
    public function test_a_subscriber_is_no_longer_capped_by_the_free_media_quota(): void
    {
        Storage::fake('s3');
        config(['quest.free_media_quota_bytes' => 100 * 1024]); // 100 KB

        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $upload = fn (string $id) => $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->post("/api/uploads/character-photos/{$id}", [
                'file' => UploadedFile::fake()->create('photo.jpg', 600, 'image/jpeg'),
            ]);

        // Free: over quota.
        $character = Character::factory()->for($user)->create();
        $upload($character->id)->assertStatus(402)->assertJsonPath('error', 'media_quota_exceeded');

        // The webhook lands.
        $this->send($this->event('INITIAL_PURCHASE', ['app_user_id' => $user->id]))->assertOk();

        $other = Character::factory()->for($user)->create();
        $upload($other->id)->assertOk();
    }

    /**
     * The scheduled `quest:generate-*-chapters` commands select on
     * `scopeWithActiveSubscription()`. With the column never written, that scope matched
     * nobody — which is why a paying subscriber received no recurring Chapter.
     */
    public function test_a_subscriber_becomes_visible_to_the_recurring_chapter_scope(): void
    {
        $user = User::factory()->optedIntoAi()->create();

        $this->assertSame(0, User::query()->withActiveSubscription()->count());

        $this->send($this->event('INITIAL_PURCHASE', ['app_user_id' => $user->id]))->assertOk();

        $this->assertSame(1, User::query()->withActiveSubscription()->count());
    }

    public function test_me_reports_the_servers_view_of_the_entitlement(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.plus', false)
            ->assertJsonPath('user.plusExpiresAt', null);

        $this->send($this->event('INITIAL_PURCHASE', ['app_user_id' => $user->id]))->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.plus', true);
    }
}
