<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored size of each backed-up binary, in bytes. Set server-side at upload
     * time (never client-fillable) and summed per user to enforce the free-tier
     * media backup quota (config `quest.free_media_quota_bytes`). Default 0 so
     * pre-existing rows don't count until re-uploaded.
     *
     * @var list<string>
     */
    private const TABLES = ['entry_attachments', 'entry_audio', 'characters'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('size_bytes')->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('size_bytes');
            });
        }
    }
};
