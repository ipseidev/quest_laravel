<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\Entry;
use App\Models\EntryAttachment;
use App\Models\EntryAudio;
use App\Models\Quest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredCommand extends Command
{
    protected $signature = 'quest:purge-expired';

    protected $description = 'Hard-delete soft-deleted content older than 30 days and tombstones older than 90 days.';

    private const CONTENT_RETENTION_DAYS = 30;

    private const TOMBSTONE_RETENTION_DAYS = 90;

    public function handle(): int
    {
        $contentCutoff = Carbon::now()->subDays(self::CONTENT_RETENTION_DAYS);
        $tombstoneCutoff = Carbon::now()->subDays(self::TOMBSTONE_RETENTION_DAYS);

        $stats = [
            'attachments_purged' => 0,
            'audio_purged' => 0,
            'characters_purged' => 0,
            'entries_purged' => 0,
            'quests_purged' => 0,
            's3_files_deleted' => 0,
            'entry_quest_tombstones_purged' => 0,
            'entry_character_tombstones_purged' => 0,
        ];

        // 1. Soft-deleted attachments older than cutoff: S3 cleanup + row delete.
        EntryAttachment::query()
            ->where('is_deleted', true)
            ->where('updated_at', '<', $contentCutoff)
            ->get()
            ->each(function (EntryAttachment $att) use (&$stats) {
                $stats['s3_files_deleted'] += $this->deleteBinary('attachments', $att->entry?->user_id, $att->id, $att->remote_uri);
                $att->delete();
                $stats['attachments_purged']++;
            });

        // 2. Soft-deleted audio.
        EntryAudio::query()
            ->where('is_deleted', true)
            ->where('updated_at', '<', $contentCutoff)
            ->get()
            ->each(function (EntryAudio $audio) use (&$stats) {
                $stats['s3_files_deleted'] += $this->deleteBinary('audio', $audio->entry?->user_id, $audio->id, $audio->remote_uri);
                $audio->delete();
                $stats['audio_purged']++;
            });

        // 3. Soft-deleted entries: pre-emptively clean up child binaries (CASCADE will remove their rows).
        Entry::query()
            ->where('is_deleted', true)
            ->where('updated_at', '<', $contentCutoff)
            ->get()
            ->each(function (Entry $entry) use (&$stats) {
                EntryAttachment::query()
                    ->where('entry_id', $entry->id)
                    ->whereNotNull('remote_uri')
                    ->get()
                    ->each(function (EntryAttachment $att) use ($entry, &$stats) {
                        $stats['s3_files_deleted'] += $this->deleteBinary('attachments', $entry->user_id, $att->id, $att->remote_uri);
                    });

                EntryAudio::query()
                    ->where('entry_id', $entry->id)
                    ->whereNotNull('remote_uri')
                    ->get()
                    ->each(function (EntryAudio $audio) use ($entry, &$stats) {
                        $stats['s3_files_deleted'] += $this->deleteBinary('audio', $entry->user_id, $audio->id, $audio->remote_uri);
                    });

                $entry->delete();
                $stats['entries_purged']++;
            });

        // 4. Soft-deleted characters: photo cleanup + row.
        Character::query()
            ->where('is_deleted', true)
            ->where('updated_at', '<', $contentCutoff)
            ->get()
            ->each(function (Character $character) use (&$stats) {
                if ($character->remote_photo_uri) {
                    $stats['s3_files_deleted'] += $this->deleteBinary('character-photos', $character->user_id, $character->id, $character->remote_photo_uri);
                }
                $character->delete();
                $stats['characters_purged']++;
            });

        // 5. Soft-deleted quests.
        $stats['quests_purged'] = Quest::query()
            ->where('is_deleted', true)
            ->where('updated_at', '<', $contentCutoff)
            ->delete();

        // 6. Tombstones older than 90 days.
        $stats['entry_quest_tombstones_purged'] = DB::table('entry_quest_tombstones')
            ->where('deleted_at', '<', $tombstoneCutoff)
            ->delete();

        $stats['entry_character_tombstones_purged'] = DB::table('entry_character_tombstones')
            ->where('deleted_at', '<', $tombstoneCutoff)
            ->delete();

        $this->info('Purge complete: '.json_encode($stats));
        Log::info('quest.retention.purge', $stats);

        return self::SUCCESS;
    }

    private function deleteBinary(string $kind, ?string $userId, string $entityId, ?string $remoteUri): int
    {
        if (! $remoteUri || ! $userId) {
            return 0;
        }
        $ext = pathinfo(parse_url($remoteUri, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'bin';
        $path = "{$kind}/{$userId}/{$entityId}.{$ext}";
        Storage::disk('s3')->delete($path);

        return 1;
    }
}
