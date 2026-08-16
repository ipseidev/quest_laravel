<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The only writer of `devices`.
 *
 * Called from the auth endpoints (where a fresh install first identifies itself)
 * and from both sync endpoints (where an app that has not logged in for months
 * reports its current version). Sync is the hottest route in the API, so this
 * class is built to usually do nothing: it reads the row by its unique key and
 * returns without writing unless a fact actually changed or the freshness window
 * elapsed.
 */
class DeviceRecorder
{
    /**
     * How stale `last_seen_at` may get before a sync is allowed to write. Chosen so
     * that a day's activity is visible at hour granularity — which is all the
     * dashboard reads it at — while a device syncing every few seconds writes four
     * times an hour instead of thousands.
     */
    private const FRESHNESS_MINUTES = 15;

    public function record(User $user, ?string $deviceId, ?string $platform, ?string $appVersion): void
    {
        if ($deviceId === null || $deviceId === '') {
            return;
        }

        $platform = $this->normalisePlatform($platform);

        try {
            $this->write($user, $deviceId, $platform, $appVersion);
        } catch (QueryException $e) {
            // Telemetry must never break the endpoint it rides on. Two concurrent
            // syncs from the same device can race on the unique key, and the correct
            // behaviour when that happens is to lose one write, not to fail a sync
            // the user is waiting on.
            Log::warning('quest.devices.record_failed', [
                'user_id' => $user->id,
                'cause' => $e->getMessage(),
            ]);
        }
    }

    private function write(User $user, string $deviceId, ?string $platform, ?string $appVersion): void
    {
        $now = Carbon::now();

        $device = Device::query()
            ->where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->first();

        if ($device === null) {
            Device::query()->create([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'platform' => $platform,
                'app_version' => $appVersion,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);

            return;
        }

        $changes = [];

        // A null from the client means "this build does not report it", not "unset
        // it". Overwriting would erase the platform of any account that upgrades and
        // later syncs from a stale binary.
        if ($platform !== null && $platform !== $device->platform) {
            $changes['platform'] = $platform;
        }

        if ($appVersion !== null && $appVersion !== $device->app_version) {
            $changes['app_version'] = $appVersion;
        }

        $stale = $device->last_seen_at === null
            || $device->last_seen_at->lt($now->copy()->subMinutes(self::FRESHNESS_MINUTES));

        if ($changes === [] && ! $stale) {
            return;
        }

        $device->fill($changes + ['last_seen_at' => $now])->save();
    }

    /**
     * Anything the client sends that is not a platform this app ships on is dropped
     * rather than stored, so the column stays a closed set the dashboard can group
     * by without a catch-all bucket appearing from a typo.
     */
    private function normalisePlatform(?string $platform): ?string
    {
        if ($platform === null) {
            return null;
        }

        $platform = strtolower(trim($platform));

        return in_array($platform, Device::PLATFORMS, true) ? $platform : null;
    }
}
