<?php

use App\Http\Controllers\AiChapterController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/password/register', [AuthController::class, 'register']);
    Route::post('/auth/password/login', [AuthController::class, 'login']);
    Route::post('/auth/apple', [AuthController::class, 'apple']);
    Route::post('/auth/google', [AuthController::class, 'google']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AuthController::class, 'updateMe']);
    Route::delete('/me', [AuthController::class, 'deleteMe']);

    Route::middleware('throttle:sync')->group(function () {
        Route::post('/sync/push', [SyncController::class, 'push']);
        Route::post('/sync/pull', [SyncController::class, 'pull']);
    });

    Route::post('/uploads/attachments/{attachmentId}', [UploadController::class, 'attachment']);
    Route::post('/uploads/audio/{audioId}', [UploadController::class, 'audio']);
    Route::post('/uploads/character-photos/{characterId}', [UploadController::class, 'characterPhoto']);

    Route::get('/ai/chapters', [AiChapterController::class, 'index']);
    Route::get('/ai/chapters/{id}', [AiChapterController::class, 'show']);

    // "Talk to Myself" — conversational AI over the user's own journal (paid + consent gated).
    Route::post('/ai/chat', [AiChatController::class, 'chat']);
    Route::get('/ai/interview-prompt', [AiChatController::class, 'interviewPrompt']);
});
