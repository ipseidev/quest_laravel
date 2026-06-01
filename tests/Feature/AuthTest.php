<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\AppleTokenVerifier;
use App\Services\Auth\GoogleTokenVerifier;
use App\Services\Auth\InvalidAppleTokenException;
use App\Services\Auth\InvalidGoogleTokenException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

        \App\Models\Quest::factory()->for($user)->create();
        \App\Models\Character::factory()->for($user)->create();
        $entry = \App\Models\Entry::factory()->for($user)->create();
        \App\Models\EntryAttachment::factory()->for($entry)->create();
        \App\Models\EntryAudio::factory()->for($entry)->create();

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
