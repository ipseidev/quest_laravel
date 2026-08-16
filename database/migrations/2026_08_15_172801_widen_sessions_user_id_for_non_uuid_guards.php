<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sessions.user_id` was declared `uuid` because every account in this schema is
 * uuid-keyed. That held for as long as nothing web-facing had a session — the
 * mobile API is Bearer-only and the marketing site sets no cookies, so the column
 * was never written at all.
 *
 * The admin panel is the first thing to log in over a session, and `admins.id` is
 * a bigint. The database session driver writes `Auth::id()` into this column on
 * every authenticated request, so a signed-in operator produced
 * `invalid input syntax for type uuid: "2"` on every page — a 500 immediately
 * after a login that had otherwise worked.
 *
 * Widened to a string rather than reshaping `admins`, for two reasons: the panel
 * is the only writer, so no data is at risk either way, and an operator account
 * already exists that a key change would destroy. A string also holds whatever id
 * type a future guard brings, which is the right contract for a column whose only
 * job is "which account owns this session".
 */
return new class extends Migration
{
    /*
     * No `->index()` on either change: Postgres rebuilds dependent indexes as part
     * of the type change, and asking for it again fails on the existing
     * `sessions_user_id_index`.
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sessions belonging to an admin hold an id no uuid cast accepts, so they
        // are cleared rather than left to fail the type change.
        DB::table('sessions')->delete();

        Schema::table('sessions', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
        });
    }
};
