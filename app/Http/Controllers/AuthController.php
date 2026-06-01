<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppleAuthRequest;
use App\Http\Requests\GoogleAuthRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
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
        } catch (InvalidAppleTokenException) {
            return response()->json([
                'error' => 'invalid_apple_token',
                'message' => 'The Apple identity token is invalid.',
            ], 401);
        }

        $sub = $claims['sub'];
        $email = $request->validated('email') ?? ($claims['email'] ?? null);

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
        } catch (InvalidGoogleTokenException) {
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

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->noContent();
    }
}
