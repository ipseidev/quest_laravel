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
        // Accept tokens minted for either the web client (the serverClientID the
        // mobile app passes, which becomes the idToken `aud`) or the native iOS
        // client id — native Google Sign-In can carry either depending on setup.
        $clientIds = array_values(array_filter([
            config('services.google.client_id'),
            config('services.google.ios_client_id'),
        ]));
        $issuers = config('services.google.issuers');

        if ($clientIds === []) {
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
        $audienceValues = is_array($audience) ? $audience : [$audience];
        if (array_intersect($audienceValues, $clientIds) === []) {
            throw new InvalidGoogleTokenException(
                'Google ID token audience mismatch (aud='.json_encode($audience)
                .', expected one of ['.implode(', ', $clientIds).']).'
            );
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
