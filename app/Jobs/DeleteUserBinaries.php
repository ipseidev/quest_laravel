<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deletes every S3 binary belonging to a user, by prefix. Dispatched after an
 * account is deleted (DELETE /me): the DB rows cascade-delete immediately, but
 * their photos and voice notes would otherwise be orphaned on S3 forever — a
 * GDPR erasure gap and an unbounded storage leak (the retention purge only
 * ever visits soft-deleted rows, never cascade-deleted ones). Runs off the
 * request so a slow or failing object store can neither block nor roll back
 * the account deletion the user asked for.
 */
class DeleteUserBinaries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Prefixes mirror BinaryUploadService::store — "{kind}/{userId}/{id}.{ext}". */
    private const KINDS = ['attachments', 'audio', 'character-photos'];

    public function __construct(public string $userId) {}

    public function handle(): void
    {
        foreach (self::KINDS as $kind) {
            $dir = "{$kind}/{$this->userId}";
            try {
                Storage::disk('s3')->deleteDirectory($dir);
            } catch (Throwable $e) {
                Log::warning('auth.delete.binary_cleanup_failed', [
                    'dir' => $dir,
                    'reason' => $e->getMessage(),
                ]);
            }
        }
    }
}
