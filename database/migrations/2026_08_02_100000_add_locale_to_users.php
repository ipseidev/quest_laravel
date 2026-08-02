<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The language the AI layer writes in. Nullable on purpose: the column is fed by
     * the client through `PATCH /api/me`, so every account that predates the app
     * release carrying that call has it null, and `User::chapterLocale()` falls back
     * rather than guessing. `app.locale` is irrelevant here — this is the user's
     * language, not the server's.
     *
     * Two characters because the only values written are the app's supported UI
     * languages ('fr', 'en'); the client resolves its 'system' preference to a
     * concrete one before pushing. Widen this if regional variants ever matter.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 2)->nullable()->after('ai_chapters_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
