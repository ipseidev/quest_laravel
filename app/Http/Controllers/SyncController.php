<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncPullRequest;
use App\Http\Requests\SyncPushRequest;
use App\Services\Devices\DeviceRecorder;
use App\Services\Sync\SyncPullService;
use App\Services\Sync\SyncPushService;
use App\Support\IsoDate;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(private readonly DeviceRecorder $devices) {}

    public function push(SyncPushRequest $request, SyncPushService $service): JsonResponse
    {
        $this->recordDevice($request);

        $result = $service->process($request->user(), $request->validated('changes', []));

        return response()->json($result);
    }

    public function pull(SyncPullRequest $request, SyncPullService $service): JsonResponse
    {
        $this->recordDevice($request);

        $since = IsoDate::parse($request->validated('lastPullTimestamp'));
        $result = $service->process($request->user(), $since);

        return response()->json($result);
    }

    /**
     * Sync is where an install that has not signed in for months reports the version
     * it is actually running, which is the only way the panel can tell who is still
     * on a build that predates EAS Update. {@see DeviceRecorder} keeps this cheap on
     * a hot route by writing only when something changed.
     */
    private function recordDevice(SyncPullRequest|SyncPushRequest $request): void
    {
        $this->devices->record(
            $request->user(),
            $request->validated('deviceId'),
            $request->validated('platform'),
            $request->validated('appVersion'),
        );
    }
}
