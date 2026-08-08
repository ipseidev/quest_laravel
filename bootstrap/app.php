<?php

use App\Exceptions\UnsupportedImageException;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RedirectLegacyHost;
use App\Http\Middleware\ValidateJsonBody;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Middleware\ShareErrorsFromSession;
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

        // Folds the legacy marketing hostname into the canonical one. Web-only:
        // the API keeps answering on every hostname it ever did, because shipped
        // app binaries have their base URL compiled in.
        $middleware->web(prepend: [
            RedirectLegacyHost::class,
        ]);

        // The `web` group serves exactly one thing here: the public marketing site
        // and the legal pages. There is no web login, no form, no flash message and
        // nothing per-visitor on any of them, so the session layer is pure cost:
        //
        //  - a database round trip per page view, for state nobody reads;
        //  - a session cookie on a purely informational site, which is the kind of
        //    non-essential cookie that pulls a French visitor into consent-banner
        //    territory for no benefit.
        //
        // Removing it means the site sets no cookies at all and can be cached whole
        // by a CDN. If a web form is ever added, put StartSession and
        // PreventRequestForgery back on that route specifically — never globally.
        //
        // PreventRequestForgery has to go with them: it writes the XSRF-TOKEN
        // cookie from the session, so it throws "Session store not set" the moment
        // StartSession is gone. SubstituteBindings stays — it is stateless and
        // needed the day a route takes a parameter.
        $middleware->web(remove: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
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

        // These two are the only handlers a *browser* can trigger — a mistyped
        // marketing URL, a malformed query. Returning null hands the request back
        // to Laravel, which renders resources/views/errors/{404,400}.blade.php.
        // Without the guard, a visitor who fat-fingers a URL is shown raw JSON.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => 'not_found',
                'message' => 'Resource not found.',
            ], 404);
        });

        $exceptions->render(function (BadRequestHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'error' => 'bad_request',
                'message' => 'Malformed request body.',
            ], 400);
        });

        // An image the server cannot decode. Answered 415 rather than 500 because the
        // client retries 5xx with exponential backoff, and no number of retries makes
        // an undecodable file decodable — the earlier 500 produced request storms.
        // The cause is logged at error level because it is an operator problem far more
        // often than a client one: a missing imagick extension, an ImageMagick policy
        // denying the coder, or libheif with no HEVC decoder all land here.
        $exceptions->render(function (UnsupportedImageException $e, Request $request) {
            Log::error('quest.upload.undecodable_image', $e->context + [
                'user_id' => $request->user()?->id,
                'path' => $request->path(),
                'cause' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'unsupported_media_type',
                'message' => 'The uploaded file type is not supported.',
            ], 415);
        });
    })->create();
