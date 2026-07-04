<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppleAuthRequest;
use App\Http\Requests\GoogleAuthRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Jobs\DeleteUserBinaries;
use App\Models\User;
use App\Services\Auth\AppleTokenVerifier;
use App\Services\Auth\GoogleTokenVerifier;
use App\Services\Auth\InvalidAppleTokenException;
use App\Services\Auth\InvalidGoogleTokenException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        if (User::query()->where('email', $request->validated('email'))->exists()) {
            return response()->json([
                'error' => 'email_taken',
                'message' => 'This email address is already in use.',
                'fields' => ['email' => ['already in use']],
            ], 409);
        }

        $user = User::query()->create([
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        $token = $user->createToken('quest-mobile')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || $user->password === null || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        $token = $user->createToken('quest-mobile')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function apple(AppleAuthRequest $request, AppleTokenVerifier $verifier): JsonResponse
    {
        try {
            $claims = $verifier->verify($request->validated('identityToken'));
        } catch (InvalidAppleTokenException $e) {
            Log::warning('auth.apple.token_rejected', ['reason' => $e->getMessage()]);

            return response()->json([
                'error' => 'invalid_apple_token',
                'message' => 'The Apple identity token is invalid.',
            ], 401);
        }

        $sub = $claims['sub'];
        // SECURITY: only ever trust the provider-verified claim for account
        // lookup/linking. The request-body `email` is attacker-controlled and
        // must NEVER be used for matching — doing so lets anyone with a valid
        // Apple token link their `sub` to (and take over) a victim's existing
        // account by passing the victim's email. Apple puts the verified email
        // in the identity token, so the claim is the authoritative source
        // (mirrors the `google()` flow below). `email`/`fullName` in the body
        // remain accepted per the API contract but are display hints only.
        $email = $claims['email'] ?? null;

        $user = User::query()->where('apple_id', $sub)->first();

        if ($user === null && $email !== null) {
            $user = User::query()->where('email', $email)->first();
            if ($user !== null) {
                $user->forceFill(['apple_id' => $sub])->save();
            }
        }

        if ($user === null) {
            $user = User::query()->create([
                'apple_id' => $sub,
                'email' => $email,
            ]);
            if ($email !== null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        $token = $user->createToken('quest-mobile')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function google(GoogleAuthRequest $request, GoogleTokenVerifier $verifier): JsonResponse
    {
        try {
            $claims = $verifier->verify($request->validated('idToken'));
        } catch (InvalidGoogleTokenException $e) {
            Log::warning('auth.google.token_rejected', ['reason' => $e->getMessage()]);

            return response()->json([
                'error' => 'invalid_google_token',
                'message' => 'The Google ID token is invalid.',
            ], 401);
        }

        $sub = $claims['sub'];
        $email = $claims['email'] ?? null;
        $emailVerified = ($claims['email_verified'] ?? false) === true;

        $user = User::query()->where('google_id', $sub)->first();

        if ($user === null && $email !== null) {
            $user = User::query()->where('email', $email)->first();
            if ($user !== null) {
                $user->forceFill(['google_id' => $sub])->save();
            }
        }

        if ($user === null) {
            $user = User::query()->create([
                'google_id' => $sub,
                'email' => $email,
            ]);
            if ($email !== null && $emailVerified) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        $token = $user->createToken('quest-mobile')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function deleteMe(Request $request): Response
    {
        $user = $request->user();
        $userId = $user->id;

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        // Content rows cascade-delete with the user, but their S3 binaries
        // would be orphaned forever (GDPR erasure gap + storage leak — the
        // retention purge only visits soft-deleted rows, never these). Clean
        // them up off-request so a slow/failing object store can't block or
        // roll back the deletion.
        DeleteUserBinaries::dispatch($userId);

        return response()->noContent();
    }
}
