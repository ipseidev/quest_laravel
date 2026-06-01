<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        // Runs in the global stack (see bootstrap/app.php) so the request is marked
        // JSON before route middleware (auth:sanctum) resolves. Scoped to /api/* so
        // the public web pages (welcome, legal) keep rendering HTML.
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
