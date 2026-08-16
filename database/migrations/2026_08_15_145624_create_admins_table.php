<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel operators, deliberately kept out of `users`.
 *
 * `users` rows are journal accounts: UUID-keyed, frequently password-less because
 * they signed in through Apple or Google, and reachable by anyone who downloads
 * the app. Hanging an `is_admin` flag off that table would mean a single missed
 * check on the mobile API — the surface that is actually exposed to the world —
 * could escalate into the panel. A separate table and guard means the two auth
 * systems share no code path at all: nothing the API does can produce a session
 * on the `admin` guard.
 *
 * Auto-increment ids rather than the UUIDs used everywhere else, because those
 * exist to let offline clients mint ids without colliding. Admins are created by
 * an operator running an artisan command, so that pressure does not apply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
