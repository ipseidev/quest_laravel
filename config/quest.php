<?php

return [
    'rate_limits' => [
        'sync' => env('QUEST_RATE_LIMIT_SYNC', 60),
        // Per-IP cap on the unauthenticated auth endpoints (register/login/
        // apple/google) — a brute-force / credential-stuffing speed bump.
        'auth' => env('QUEST_RATE_LIMIT_AUTH', 10),
    ],
];
