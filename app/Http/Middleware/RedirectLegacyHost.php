<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consolidates the site onto one hostname.
 *
 * `thequesting.app` was the original host: it is where the legal pages the App
 * Store submission points at still live, and it must keep serving `/api`,
 * because the shipped mobile binaries have that base URL compiled in. The public
 * site, though, is branded Nacre and canonical on its own domain — two hostnames
 * answering the same marketing HTML would split its ranking signals.
 *
 * So: a safe request for a *page* on a legacy host is redirected permanently to
 * the canonical origin, preserving path and query. `/api` and the health check
 * are never touched.
 *
 * Driven by an explicit allowlist (`site.legacy_hosts`) rather than by comparing
 * against the canonical host, so local development, tests, and preview
 * deployments are unaffected without needing an environment check.
 */
class RedirectLegacyHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $legacyHosts = (array) config('site.legacy_hosts', []);

        if ($legacyHosts === [] || ! in_array($request->getHost(), $legacyHosts, true)) {
            return $next($request);
        }

        // Only redirect reads. A POST would lose its body on a 301, and nothing
        // on the marketing site is written to anyway.
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        // The API and the health probe are addressed by host on purpose by
        // clients that cannot be updated. Leave them exactly where they are.
        if ($request->is('api/*') || $request->is('up')) {
            return $next($request);
        }

        return redirect(config('site.url').$request->getRequestUri(), 301);
    }
}
