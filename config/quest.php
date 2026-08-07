<?php

return [
    'rate_limits' => [
        'sync' => env('QUEST_RATE_LIMIT_SYNC', 60),
        // Per-IP cap on the unauthenticated auth endpoints (register/login/
        // apple/google) — a brute-force / credential-stuffing speed bump.
        'auth' => env('QUEST_RATE_LIMIT_AUTH', 10),
        // RevenueCat's webhook, keyed by IP. Deliberately generous: RevenueCat
        // retries with backoff and can burst after an outage, and a throttled
        // billing event means a paying subscriber stays on the free tier. The
        // shared-secret check is what actually protects this endpoint.
        'webhook' => env('QUEST_RATE_LIMIT_WEBHOOK', 300),
    ],

    // Cloud-media backup quota for FREE accounts, in bytes. Text/metadata backup
    // is unmetered (negligible cost); only binaries (photos/audio) count. Nacre
    // Plus subscribers are unlimited. Photos are already downscaled client-side
    // (~2048px/0.7 JPEG) and audio is a 64 kbps voice profile, so 500 MB is a
    // generous safety net (thousands of photos / many hours of notes). Tune via
    // QUEST_FREE_MEDIA_QUOTA_MB without a code change.
    //
    // VIDEO is not metered by this quota and never counts against it: video cloud
    // backup is a Nacre Plus feature outright (`UploadController::video` refuses
    // it for free accounts), so a free clip never reaches the server. That split
    // is deliberate — one high-quality minute of phone video can weigh a third of
    // this whole budget, which would make the quota bite at the third clip and
    // read as "the app is broken" rather than "this is the free tier".
    'free_media_quota_bytes' => (int) env('QUEST_FREE_MEDIA_QUOTA_MB', 500) * 1024 * 1024,
];
