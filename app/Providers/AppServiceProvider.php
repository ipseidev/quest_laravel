<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('sync', function (Request $request) {
            $perMinute = (int) config('quest.rate_limits.sync', 60);

            return Limit::perMinute($perMinute)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'rate_limited',
                        'message' => 'Too many sync requests.',
                    ], 429)->header('Retry-After', '60');
                });
        });

        /*
         * RevenueCat's webhook. Keyed by IP, and far looser than the auth limiter on
         * purpose: RevenueCat retries with backoff and can burst after an outage, and a
         * throttled billing event means a paying subscriber silently stays on the free
         * tier. The shared-secret check in the controller is the real gate; this only
         * bounds how hard an unauthenticated caller can hammer the endpoint.
         *
         * No custom 429 envelope: RevenueCat reads the status code, not the body, and
         * will retry.
         */
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute((int) config('quest.rate_limits.webhook', 300))
                ->by($request->ip());
        });

        // Unauthenticated auth endpoints: keyed by IP (there is no user yet).
        // Blunts password brute-force / credential-stuffing. Same 429 envelope
        // as sync so the client's existing Retry-After handling applies.
        RateLimiter::for('auth', function (Request $request) {
            $perMinute = (int) config('quest.rate_limits.auth', 10);

            return Limit::perMinute($perMinute)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'rate_limited',
                        'message' => 'Too many authentication attempts.',
                    ], 429)->header('Retry-After', '60');
                });
        });
    }
}
