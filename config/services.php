<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'jwks_url' => 'https://appleid.apple.com/auth/keys',
        'issuer' => 'https://appleid.apple.com',
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID'),
        'jwks_url' => 'https://www.googleapis.com/oauth2/v3/certs',
        'issuers' => ['https://accounts.google.com', 'accounts.google.com'],
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        // Chapter generation hard-depends on structured outputs (output_config.format
        // json_schema). The default MUST be a model that supports it — Sonnet 4.6 does
        // NOT (it 400s on the format the code requires), so the default is Sonnet 5.
        'chapter_model' => env('ANTHROPIC_CHAPTER_MODEL', 'claude-sonnet-5'),
        // Shared budget for adaptive thinking + the JSON output; too small and a long
        // think truncates the JSON (stop_reason=max_tokens) and the chapter is lost.
        'chapter_max_tokens' => (int) env('ANTHROPIC_CHAPTER_MAX_TOKENS', 16000),
        'chapters_enabled' => env('QUEST_CHAPTERS_ENABLED', false),
        // "Talk to Myself" — interactive chat over the user's own entries. Free-form
        // text (no json_schema), multi-turn, synchronous. Default Sonnet 5 (chat needs
        // near-Opus quality at a reasonable cost).
        'chat_model' => env('ANTHROPIC_CHAT_MODEL', 'claude-sonnet-5'),
        'chat_max_tokens' => (int) env('ANTHROPIC_CHAT_MAX_TOKENS', 4096),
        // The interviewer's daily question: one short line. Thinking is disabled for
        // this (very frequent, cached) call, so a small budget is plenty. Kept as its
        // own model knob so it can be dropped to Haiku for cost without touching chat.
        'interview_model' => env('ANTHROPIC_INTERVIEW_MODEL', 'claude-sonnet-5'),
        'interview_max_tokens' => (int) env('ANTHROPIC_INTERVIEW_MAX_TOKENS', 512),
        // Global kill switch for the chat + interviewer endpoints (mirrors chapters_enabled).
        'chat_enabled' => env('QUEST_CHAT_ENABLED', false),
    ],

];
