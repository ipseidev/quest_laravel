<?php

namespace App\Services\Upload;

use App\Models\Character;
use App\Models\Entry;
use App\Models\EntryAttachment;
use App\Models\EntryAudio;
use App\Models\User;

/**
 * Enforces the free-tier cloud-media backup quota. Text/metadata backup is
 * unmetered; among binaries, only photos, voice notes and character photos
 * count. Nacre Plus subscribers are unlimited.
 *
 * VIDEO is deliberately absent from the sum. Video cloud backup is a Plus
 * feature outright (`UploadController::video` refuses free accounts), so a free
 * clip never reaches the server and there is nothing to meter; for a subscriber
 * `canStore()` short-circuits before the sum is ever computed. Clips a free
 * account backed up before that rule existed are grandfathered: they stay on the
 * server and no longer consume the account's photo/audio budget.
 */
class BackupQuotaService
{
    /** Free-tier cloud-media budget in bytes. */
    public function quotaBytes(): int
    {
        return (int) config('quest.free_media_quota_bytes');
    }

    /**
     * Total bytes of the user's NON-deleted backed-up binaries. Summing only
     * live rows means a delete frees the user's quota immediately (the bytes
     * linger on the disk until retention purges them, which is acceptable slack).
     * Global scopes are dropped so the figure is correct regardless of the
     * ambient authenticated user.
     */
    public function usedBytes(User $user): int
    {
        $entryIds = Entry::query()->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->pluck('id');

        $attachments = EntryAttachment::query()->withoutGlobalScopes()
            ->whereIn('entry_id', $entryIds)
            ->where('is_deleted', false)
            ->sum('size_bytes');

        $audio = EntryAudio::query()->withoutGlobalScopes()
            ->whereIn('entry_id', $entryIds)
            ->where('is_deleted', false)
            ->sum('size_bytes');

        $characterPhotos = Character::query()->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->sum('size_bytes');

        return (int) ($attachments + $audio + $characterPhotos);
    }

    /**
     * Whether the user may store an incoming binary of the given size. Nacre Plus
     * subscribers are unlimited; free accounts are held to the quota.
     */
    public function canStore(User $user, int $incomingBytes): bool
    {
        if ($user->hasActiveSubscription()) {
            return true;
        }

        return $this->usedBytes($user) + $incomingBytes <= $this->quotaBytes();
    }
}
