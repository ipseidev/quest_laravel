<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing entitlement + the one-off AI-sample marker.
     *
     * `subscription_product_id` null means a FREE account (local + cloud backup,
     * but no continuous multi-device sync and no recurring AI Chapters). A set
     * product id with a future `subscription_expires_at` — or a null expiry,
     * which marks a non-expiring "lifetime" entitlement — means an active
     * subscription. A past expiry means the subscription lapsed.
     *
     * These are fed by the RevenueCat webhook (deferred — see MONETIZATION_PLAN
     * P2.1). Until then they stay null and `User::hasActiveSubscription()` returns
     * false for everyone, which is correct: no one has paid yet.
     *
     * `sample_chapter_generated_at` is stamped the first (and only) time a free
     * account generates its complimentary sample Chapter (MONETIZATION_PLAN P1.3),
     * so it can never be produced twice for a non-subscriber.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_product_id')->nullable()->after('ai_chapters_opt_in');
            $table->timestamp('subscription_expires_at', 3)->nullable()->after('subscription_product_id');
            $table->timestamp('sample_chapter_generated_at', 3)->nullable()->after('subscription_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_product_id',
                'subscription_expires_at',
                'sample_chapter_generated_at',
            ]);
        });
    }
};
