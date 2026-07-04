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
