<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AppleTokenVerifier
{
    /**
     * Verify an Apple identity token and return its claims.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidAppleTokenException
     */
    public function verify(string $identityToken): array
    {
        $clientId = config('services.apple.client_id');
        $issuer = config('services.apple.issuer');

        if (empty($clientId)) {
            throw new InvalidAppleTokenException('Apple client ID is not configured.');
        }

        try {
            $keys = JWK::parseKeySet($this->fetchJwks());
            $decoded = (array) JWT::decode($identityToken, $keys);
        } catch (Throwable $e) {
            throw new InvalidAppleTokenException('Apple identity token failed verification.', previous: $e);
        }

        if (($decoded['iss'] ?? null) !== $issuer) {
            throw new InvalidAppleTokenException('Apple identity token has an unexpected issuer.');
        }

        $audience = $decoded['aud'] ?? null;
        if ($audience !== $clientId && (! is_array($audience) || ! in_array($clientId, $audience, true))) {
            throw new InvalidAppleTokenException('Apple identity token audience mismatch.');
        }

        if (empty($decoded['sub'])) {
            throw new InvalidAppleTokenException('Apple identity token is missing the sub claim.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJwks(): array
    {
        return Cache::remember('apple.jwks', now()->addHour(), function () {
            return Http::get(config('services.apple.jwks_url'))->throw()->json();
        });
    }
}
