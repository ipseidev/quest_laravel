<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Entry;
use App\Models\EntryAttachment;
use App\Models\EntryAudio;
use App\Models\Quest;
use App\Models\User;
use App\Services\Auth\AppleTokenVerifier;
use App\Services\Auth\GoogleTokenVerifier;
use App\Services\Auth\InvalidAppleTokenException;
use App\Services\Auth\InvalidGoogleTokenException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function deviceId(): string
    {
        return (string) Str::uuid();
    }

    public function test_unauthenticated_api_request_without_json_accept_returns_401_json(): void
    {
        // Regression: a protected /api route hit WITHOUT `Accept: application/json`
        // must still return the 401 JSON envelope, never a 500 (the framework must
        // not fall through to the HTML login-redirect, which has no `login` route).
        $response = $this->get('/api/me', ['Accept' => 'text/html']);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }

    public function test_a1_register_with_valid_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/password/register', [
            'email' => 'user@example.com',
            'password' => 'password123',
            'deviceId' => $this->deviceId(),
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email', 'createdAt'], 'token'])
            ->assertJsonPath('user.email', 'user@example.com');

        $user = User::query()->where('email', 'user@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_a2_register_with_same_email_twice_returns_409(): void
    {
        $payload = [
            'email' => 'dupe@example.com',
            'password' => 'password123',
            'deviceId' => $this->deviceId(),
        ];

        $this->postJson('/api/auth/password/register', $payload)->assertOk();

        $this->postJson('/api/auth/password/register', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error', 'email_taken')
            ->assertJsonPath('fields.email.0', 'already in use');
    }

    public function test_a3_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/password/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
            'deviceId' => $this->deviceId(),
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email', 'createdAt'], 'token'])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_a4_login_with_wrong_password_returns_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/password/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
            'deviceId' => $this->deviceId(),
        ])
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_credentials');
    }

    public function test_a5_login_with_nonexistent_email_returns_invalid_credentials(): void
    {
        $this->postJson('/api/auth/password/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
            'deviceId' => $this->deviceId(),
        ])
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_credentials');
    }

    public function test_a10_get_me_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('quest-mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_a11_get_me_with_revoked_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('quest-mobile')->plainTextToken;

        $user->tokens()->delete();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthenticated');
    }

    public function test_a12_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->create();
        $tokenA = $user->createToken('device-a')->plainTextToken;
        $tokenB = $user->createToken('device-b')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/me')
            ->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->getJson('/api/me')
            ->assertOk();
    }

    public function test_a13_delete_me_purges_user_and_all_data_and_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('quest-mobile')->plainTextToken;

        Quest::factory()->for($user)->create();
        Character::factory()->for($user)->create();
        $entry = Entry::factory()->for($user)->create();
        EntryAttachment::factory()->for($entry)->create();
        EntryAudio::factory()->for($entry)->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/me')
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('entries', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('quests', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('characters', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('entry_attachments', ['entry_id' => $entry->id]);
        $this->assertDatabaseMissing('entry_audio', ['entry_id' => $entry->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_a14_delete_me_removes_the_users_s3_binaries_but_not_other_users(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $token = $user->createToken('quest-mobile')->plainTextToken;
        $other = User::factory()->create();

        // Seed binaries under the "{kind}/{userId}/{id}.{ext}" convention that
        // BinaryUploadService::store writes.
        Storage::disk('s3')->put("attachments/{$user->id}/a1.jpg", 'x');
        Storage::disk('s3')->put("audio/{$user->id}/au1.m4a", 'x');
        Storage::disk('s3')->put("character-photos/{$user->id}/c1.jpg", 'x');
        // A different user's binary that must survive (no cross-user deletion).
        Storage::disk('s3')->put("attachments/{$other->id}/keep.jpg", 'x');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/me')
            ->assertNoContent();

        // Queue is sync in tests, so DeleteUserBinaries ran inline.
        Storage::disk('s3')->assertMissing("attachments/{$user->id}/a1.jpg");
        Storage::disk('s3')->assertMissing("audio/{$user->id}/au1.m4a");
        Storage::disk('s3')->assertMissing("character-photos/{$user->id}/c1.jpg");

        Storage::disk('s3')->assertExists("attachments/{$other->id}/keep.jpg");
    }

    public function test_register_validation_error_format(): void
    {
        $this->postJson('/api/auth/password/register', [
            'email' => 'not-an-email',
            'password' => 'short',
            'deviceId' => 'not-a-uuid',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation')
            ->assertJsonStructure(['error', 'message', 'fields' => ['email', 'password', 'deviceId']]);
    }

    public function test_a6_apple_signin_creates_new_user(): void
    {
        $this->mock(AppleTokenVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->andReturn([
                'sub' => 'apple-sub-new',
                'email' => 'apple-new@test.io',
            ]);
        });

        $response = $this->postJson('/api/auth/apple', [
            'identityToken' => 'apple.identity.jwt',
            'authorizationCode' => 'c123',
            'fullName' => ['givenName' => 'Apple', 'familyName' => 'User'],
            'email' => 'apple-new@test.io',
            'deviceId' => $this->deviceId(),
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email', 'createdAt'], 'token'])
            ->assertJsonPath('user.email', 'apple-new@test.io');

        $user = User::query()->where('apple_id', 'apple-sub-new')->first();
        $this->assertNotNull($user);
        $this->assertSame('apple-new@test.io', $user->email);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_a7_apple_signin_links_existing_email_user(): void
    {
        $user = User::factory()->create([
            'email' => 'shared@test.io',
            'password' => Hash::make('password123'),
        ]);
        $this->assertNull($user->apple_id);

        $this->mock(AppleTokenVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->andReturn([
                'sub' => 'apple-sub-link',
                'email' => 'shared@test.io',
            ]);
        });

        $this->postJson('/api/auth/apple', [
            'identityToken' => 'apple.identity.jwt',
            'email' => 'shared@test.io',
            'deviceId' => $this->deviceId(),
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $user->refresh();
        $this->assertSame('apple-sub-link', $user->apple_id);
        $this->assertNotNull($user->password); // existing password preserved
    }

    public function test_a7b_apple_signin_ignores_body_email_and_cannot_hijack_account(): void
    {
        // SECURITY REGRESSION: an attacker with a valid Apple token (their own
        // `sub`) must not be able to take over a victim's existing account by
        // supplying the victim's email in the request body. Only the verified
        // token claim may drive account matching/linking.
        $victim = User::factory()->create([
            'email' => 'victim@test.io',
            'password' => Hash::make('victim-password'),
        ]);

        // Attacker's token: their own sub + their own relay email (differs from
        // the body email they try to inject).
        $this->mock(AppleTokenVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->andReturn([
                'sub' => 'attacker-apple-sub',
                'email' => 'attacker@privaterelay.appleid.com',
            ]);
        });

        $response = $this->postJson('/api/auth/apple', [
            'identityToken' => 'attacker.identity.jwt',
            'email' => 'victim@test.io', // malicious: not the verified claim
            'deviceId' => $this->deviceId(),
        ])->assertOk();

        // The attacker gets their OWN new account, keyed on the verified claim.
        $this->assertNotSame($victim->id, $response->json('user.id'));
        $this->assertSame('attacker@privaterelay.appleid.com', $response->json('user.email'));

        // The victim's account is untouched: not linked, password intact.
        $victim->refresh();
        $this->assertNull($victim->apple_id);
        $this->assertNotNull($victim->password);

        // The attacker's sub landed on a distinct account, never the victim's.
        $attacker = User::query()->where('apple_id', 'attacker-apple-sub')->first();
        $this->assertNotNull($attacker);
        $this->assertNotSame($victim->id, $attacker->id);
    }

    public function test_a7c_apple_signin_with_no_claim_email_does_not_link_via_body_email(): void
    {
        // Sharpest case: attacker hides their Apple email (no `email` claim) and
        // injects the victim's email in the body. Linking must not happen.
        $victim = User::factory()->create([
            'email' => 'target@test.io',
            'password' => Hash::make('secret'),
        ]);

        $this->mock(AppleTokenVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->andReturn([
                'sub' => 'no-email-sub',
            ]);
        });

        $response = $this->postJson('/api/auth/apple', [
            'identityToken' => 'attacker.identity.jwt',
            'email' => 'target@test.io',
            'deviceId' => $this->deviceId(),
        ])->assertOk();

        $this->assertNotSame($victim->id, $response->json('user.id'));

        $victim->refresh();
        $this->assertNull($victim->apple_id);

        // A fresh account was created with no email (claim had none).
        $created = User::query()->where('apple_id', 'no-email-sub')->first();
        $this->assertNotNull($created);
        $this->assertNull($created->email);
    }

    public function test_a8_apple_signin_invalid_token_returns_401(): void
    {
        $this->mock(AppleTokenVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->andThrow(new InvalidAppleTokenException('bad signature'));
        });

        $this->postJson('/api/auth/apple', [
            'identityToken' => 'bogus',
            'deviceId' => $this->deviceId(),
        ])
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_apple_token');
    }

    public function test_a9_google_signin_creates_new_user(): void
    {
        $this->mock(GoogleTokenVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->andReturn([
                'sub' => 'google-sub-new',
                'email' => 'g-new@test.io',
                'email_verified' => true,
            ]);
        });

        $this->postJson('/api/auth/google', [
            'idToken' => 'google.id.token',
            'deviceId' => $this->deviceId(),
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'g-new@test.io');

        $user = User::query()->where('google_id', 'google-sub-new')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_a9_google_signin_links_existing_email_user(): void
    {
        $user = User::factory()->create([
            'email' => 'g-shared@test.io',
            'password' => Hash::make('password123'),
        ]);

        $this->mock(GoogleTokenVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->andReturn([
                'sub' => 'google-sub-link',
                'email' => 'g-shared@test.io',
                'email_verified' => true,
            ]);
        });

        $this->postJson('/api/auth/google', [
            'idToken' => 'g.id.token',
            'deviceId' => $this->deviceId(),
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $user->refresh();
        $this->assertSame('google-sub-link', $user->google_id);
    }

    public function test_a9_google_signin_invalid_token_returns_401(): void
    {
        $this->mock(GoogleTokenVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')->andThrow(new InvalidGoogleTokenException('bad audience'));
        });

        $this->postJson('/api/auth/google', [
            'idToken' => 'bogus',
            'deviceId' => $this->deviceId(),
        ])
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_google_token');
    }

    public function test_user_id_is_uuid_v4_format(): void
    {
        $response = $this->postJson('/api/auth/password/register', [
            'email' => 'v4@test.io',
            'password' => 'password123',
            'deviceId' => (string) Str::uuid(),
        ]);

        $id = $response->json('user.id');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id
        );
    }
}
