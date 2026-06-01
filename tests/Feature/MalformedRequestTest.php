<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MalformedRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_i2_malformed_json_body_returns_400_bad_request(): void
    {
        $response = $this->call(
            'POST',
            '/api/auth/password/register',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            '{"email":"a@b.com", "password":"abc'  // unterminated JSON
        );

        $response->assertStatus(400)
            ->assertJsonPath('error', 'bad_request');
    }

    public function test_valid_empty_body_is_not_400(): void
    {
        // Empty body on a POST that requires no fields should not hit the JSON validator.
        // We use logout (auth required), expect 401 (no token) — proving validateJson does not
        // trip on empty body.
        $response = $this->call(
            'POST',
            '/api/auth/logout',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response->assertStatus(401);
    }
}
