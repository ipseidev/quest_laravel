<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Videos attached to an entry — the third media kind next to
     * `entry_attachments` (photos) and `entry_audio` (voice notes), and shaped
     * exactly like them so the sync engine treats all three identically.
     *
     * `size_bytes` is included from the start (the other two got it retroactively
     * in `add_size_bytes_to_binaries`): it is set server-side at upload time and
     * summed per user to enforce the free-tier media quota. It matters more here
     * than anywhere else, since one clip can weigh as much as a hundred photos.
     *
     * There is deliberately NO poster-frame column. The client extracts a poster
     * locally and keeps it local — it is derived data, regenerable from the video
     * on any device, so storing or transferring it would be pure cost.
     */
    public function up(): void
    {
        Schema::create('entry_videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entry_id')->constrained()->cascadeOnDelete();
            $table->string('uri', 2048)->default('');
            $table->string('remote_uri', 2048)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps(3);

            $table->index(['is_deleted', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_videos');
    }
};
