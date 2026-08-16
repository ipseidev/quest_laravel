<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the server knows about the app installations behind an account.
 *
 * Every authenticated request already carries a client-generated `deviceId`; it
 * was validated and thrown away. Keeping it, alongside the platform and app
 * version, is what makes "is Android working?" and "who is still on a build that
 * predates EAS Update?" answerable at all — neither question can be answered from
 * `users`, which records no platform.
 *
 * Deliberately not a device *fingerprint*: no IP, no user agent, no model name.
 * The columns here are the two facts the dashboard needs and nothing that would
 * turn a journal account into a tracked identity.
 *
 * Keyed by (user_id, device_id) rather than device_id alone, so an iPhone handed
 * to a second account produces a second row instead of silently reassigning the
 * first. `last_seen_at` is a server timestamp — unlike `entries.updated_at`, which
 * sync writes verbatim from the client and therefore cannot be trusted as an
 * activity signal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_id');

            // Nullable because binaries already in production do not report it.
            // Those rows are counted as "unknown" on the dashboard rather than
            // being folded into either platform.
            $table->string('platform', 16)->nullable();
            $table->string('app_version', 32)->nullable();

            $table->timestamp('first_seen_at', 3)->nullable();
            $table->timestamp('last_seen_at', 3)->nullable();
            $table->timestamps(3);

            $table->unique(['user_id', 'device_id']);
            $table->index('platform');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
