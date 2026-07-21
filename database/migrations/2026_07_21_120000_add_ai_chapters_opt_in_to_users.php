<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user consent for the optional AI layer (Chapters / "Le Chapitre").
     * Default false — the app is usable with no AI, and entry text is only ever
     * sent to the model for users who explicitly opt in. Both chapter generation
     * and the /ai/chapters read endpoint gate on this flag.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('ai_chapters_opt_in')->default(false)->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ai_chapters_opt_in');
        });
    }
};
