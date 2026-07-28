<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordering marker for RevenueCat webhook delivery.
     *
     * Webhooks are retried and can arrive out of order: a RENEWAL delivered after the
     * CANCELLATION that followed it would silently re-grant a subscription the user
     * has already ended. `App\Services\Billing\RevenueCatEntitlements` therefore drops
     * any event older than the last one applied to that account.
     *
     * Stored as integer milliseconds rather than a `timestamp(3)` because `User` has no
     * `$dateFormat` override — its datetimes are written at second precision, so a
     * timestamp column would collapse two events landing in the same second and lose
     * exactly the ordering this column exists to preserve. It is a comparison key,
     * never displayed, and milliseconds is the unit RevenueCat sends.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_event_at_ms')
                ->nullable()
                ->after('subscription_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('subscription_event_at_ms');
        });
    }
};
