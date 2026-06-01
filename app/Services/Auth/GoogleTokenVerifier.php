<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleTokenVerifier
{
    /**
     * Verify a Google ID token and return its claims.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidGoogleTokenException
     */
    public function verify(string $idToken): array
    {
        $clientId = config('services.google.client_id');
        $issuers = config('services.google.issuers');

        if (empty($clientId)) {
            throw new InvalidGoogleTokenException('Google client ID is not configured.');
        }

        try {
            $keys = JWK::parseKeySet($this->fetchJwks());
            $decoded = (array) JWT::decode($idToken, $keys);
        } catch (Throwable $e) {
            throw new InvalidGoogleTokenException('Google ID token failed verification.', previous: $e);
        }

        if (! in_array($decoded['iss'] ?? null, $issuers, true)) {
            throw new InvalidGoogleTokenException('Google ID token has an unexpected issuer.');
        }

        $audience = $decoded['aud'] ?? null;
        if ($audience !== $clientId && (! is_array($audience) || ! in_array($clientId, $audience, true))) {
            throw new InvalidGoogleTokenException('Google ID token audience mismatch.');
        }

        if (empty($decoded['sub'])) {
            throw new InvalidGoogleTokenException('Google ID token is missing the sub claim.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJwks(): array
    {
        return Cache::remember('google.jwks', now()->addHour(), function () {
            return Http::get(config('services.google.jwks_url'))->throw()->json();
        });
    }
}
