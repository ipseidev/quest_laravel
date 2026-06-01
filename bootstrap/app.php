<?php

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
        $middleware->api(append: [
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\ValidateJsonBody::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
