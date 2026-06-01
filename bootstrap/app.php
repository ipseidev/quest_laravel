<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ValidateJsonBody;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ForceJsonResponse runs in the GLOBAL stack — before route middleware like
        // auth:sanctum — so an unauthenticated /api request is marked JSON before auth
        // resolves. Otherwise auth computes the HTML guest-redirect and 500s on the
        // missing `login` route. The middleware self-scopes to /api/* paths.
        $middleware->prepend(ForceJsonResponse::class);

        $middleware->api(append: [
            ValidateJsonBody::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render every /api/* exception as JSON regardless of the client's Accept
        // header. Without this, an unauthenticated request lacking `Accept:
        // application/json` falls through to the HTML login-redirect path and 500s
        // (no `login` route exists). Keeps the "every API response is JSON" contract.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            return response()->json([
                'error' => 'validation',
                'message' => 'The given data was invalid.',
                'fields' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'error' => 'unauthenticated',
                'message' => 'Authentication required.',
            ], 401);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Resource not found.',
            ], 404);
        });

        $exceptions->render(function (BadRequestHttpException $e, Request $request) {
            return response()->json([
                'error' => 'bad_request',
                'message' => 'Malformed request body.',
            ], 400);
        });
    })->create();
