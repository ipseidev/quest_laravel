<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Device reporting rides on endpoints that binaries already in production call
 * every few minutes. The invariant these tests protect is that it stays additive:
 * an old client must behave exactly as it did, and a new one must not be able to
 * make a sync fail by reporting something unexpected.
 */
class DeviceCaptureTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $extra
     */
    private function push(array $extra = [], ?string $deviceId = null, ?string $token = null): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.($token ?? $this->token))
            ->postJson('/api/sync/push', array_merge([
                'deviceId' => $deviceId ?? (string) Str::uuid(),
                'changes' => [],
            ], $extra));
    }

    private function device(?User $user = null): Device
    {
        return Device::query()->where('user_id', ($user ?? $this->user)->id)->sole();
    }

    public function test_signing_in_records_the_platform_and_version(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/auth/password/login', [
            'email' => 'a@example.com',
            'password' => 'password',
            'deviceId' => (string) Str::uuid(),
            'platform' => 'android',
            'appVersion' => '1.0.4',
        ])->assertOk();

        $device = $this->device($user);

        $this->assertSame('android', $device->platform);
        $this->assertSame('1.0.4', $device->app_version);
        $this->assertNotNull($device->first_seen_at);
    }

    /**
     * The compatibility guarantee. Binaries live on the App Store today send neither
     * field; they must keep working and simply report no platform.
     */
    public function test_a_client_that_reports_nothing_still_syncs(): void
    {
        $this->push()->assertOk();

        $device = $this->device();

        $this->assertNull($device->platform);
        $this->assertNull($device->app_version);
        $this->assertNotNull($device->last_seen_at);
    }

    /**
     * A user upgrades, then syncs once from an older install that reports nothing.
     * The null must not erase what the newer build already established.
     */
    public function test_a_silent_client_does_not_erase_a_known_platform(): void
    {
        $deviceId = (string) Str::uuid();

        $this->push(['platform' => 'ios', 'appVersion' => '1.0.4'], $deviceId)->assertOk();
        $this->push([], $deviceId)->assertOk();

        $device = $this->device();

        $this->assertSame('ios', $device->platform);
        $this->assertSame('1.0.4', $device->app_version);
    }

    /**
     * Validation is deliberately loose on these fields — a client sending an
     * unexpected value must not have its sync rejected — so the closed set is
     * enforced when writing instead.
     */
    public function test_an_unrecognised_platform_is_dropped_not_stored(): void
    {
        $this->push(['platform' => 'windows-phone'])->assertOk();

        $this->assertNull($this->device()->platform);
    }

    public function test_platform_casing_from_the_client_is_normalised(): void
    {
        $this->push(['platform' => 'iOS'])->assertOk();

        $this->assertSame('ios', $this->device()->platform);
    }

    /**
     * Sync is the hottest route in the API. A device that has changed nothing and
     * was seen a moment ago must not produce a write on every request.
     */
    public function test_an_unchanged_device_is_not_rewritten_on_every_sync(): void
    {
        $deviceId = (string) Str::uuid();
        $payload = ['platform' => 'ios', 'appVersion' => '1.0.4'];

        $this->push($payload, $deviceId)->assertOk();
        $seenAt = $this->device()->last_seen_at;

        Carbon::setTestNow(Carbon::now()->addMinutes(5));
        $this->push($payload, $deviceId)->assertOk();

        $this->assertTrue(
            $seenAt->equalTo($this->device()->last_seen_at),
            'A sync within the freshness window rewrote the device row.',
        );

        // Past the window it does write, otherwise activity would never age.
        Carbon::setTestNow(Carbon::now()->addMinutes(20));
        $this->push($payload, $deviceId)->assertOk();

        $this->assertTrue($seenAt->lessThan($this->device()->last_seen_at));

        Carbon::setTestNow();
    }

    /**
     * A version change is reported immediately rather than waiting out the freshness
     * window — an upgrade is exactly the event the panel is watching for.
     */
    public function test_a_version_change_is_written_straight_away(): void
    {
        $deviceId = (string) Str::uuid();

        $this->push(['platform' => 'ios', 'appVersion' => '1.0.4'], $deviceId)->assertOk();

        Carbon::setTestNow(Carbon::now()->addMinutes(2));
        $this->push(['platform' => 'ios', 'appVersion' => '1.0.5'], $deviceId)->assertOk();

        $this->assertSame('1.0.5', $this->device()->app_version);

        Carbon::setTestNow();
    }

    public function test_two_accounts_on_one_handset_are_two_rows(): void
    {
        $deviceId = (string) Str::uuid();
        $other = User::factory()->create();

        $this->push(['platform' => 'ios'], $deviceId)->assertOk();
        $this->push(
            ['platform' => 'ios'],
            $deviceId,
            $other->createToken('mobile')->plainTextToken,
        )->assertOk();

        $this->assertSame(2, Device::query()->where('device_id', $deviceId)->count());
    }
}
