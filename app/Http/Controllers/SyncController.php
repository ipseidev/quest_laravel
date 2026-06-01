<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncPullRequest;
use App\Http\Requests\SyncPushRequest;
use App\Services\Sync\SyncPullService;
use App\Services\Sync\SyncPushService;
use App\Support\IsoDate;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function push(SyncPushRequest $request, SyncPushService $service): JsonResponse
    {
        $result = $service->process($request->user(), $request->validated('changes', []));

        return response()->json($result);
    }

    public function pull(SyncPullRequest $request, SyncPullService $service): JsonResponse
    {
        $since = IsoDate::parse($request->validated('lastPullTimestamp'));
        $result = $service->process($request->user(), $since);

        return response()->json($result);
    }
}
