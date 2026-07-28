<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['email', 'password', 'apple_id', 'google_id'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function quests(): HasMany
    {
        return $this->hasMany(Quest::class);
    }

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ai_chapters_opt_in' => 'boolean',
            'subscription_expires_at' => 'datetime',
            // Postgres returns bigint as a string over PDO; cast so the webhook's
            // out-of-order comparison is an integer comparison either way.
            'subscription_event_at_ms' => 'integer',
            'sample_chapter_generated_at' => 'datetime',
        ];
    }

    /**
     * Whether this user may use AI features (chat, interviewer, and — once the
     * subscription gate is retrofitted — chapters). AI is a PAID feature: it
     * requires an active subscription AND explicit consent. Every AI endpoint
     * gates on this single method so the paid + consent policy lives in one place.
     */
    public function hasAiAccess(): bool
    {
        return $this->hasActiveSubscription() && $this->ai_chapters_opt_in;
    }

    /**
     * Whether this account holds an active paid entitlement ("Nacre Plus").
     *
     * Source of truth is `subscription_product_id` + `subscription_expires_at`, written
     * only by `App\Services\Billing\RevenueCatEntitlements` from RevenueCat's webhook
     * (`POST /api/webhooks/revenuecat`). A null product id = free account. A set product
     * id with a future expiry — or a null expiry, which marks a non-expiring "lifetime"
     * entitlement — is active; a past expiry means it lapsed. Chat + interviewer gate on
     * this via hasAiAccess(); chapters are retrofitted to it in P1.3.
     *
     * Note that a cancelled-but-not-yet-expired subscription is still active here, which
     * is correct: turning off auto-renew does not end the period already paid for.
     */
    public function hasActiveSubscription(): bool
    {
        if ($this->subscription_product_id === null) {
            return false;
        }

        return $this->subscription_expires_at === null
            || $this->subscription_expires_at->isFuture();
    }

    /**
     * Constrain to accounts with an active paid entitlement — the query-level
     * mirror of hasActiveSubscription(), used by the scheduled chapter commands
     * so only paying users get recurring AI Chapters.
     */
    public function scopeWithActiveSubscription(Builder $query): void
    {
        $query->whereNotNull('subscription_product_id')
            ->where(function (Builder $inner) {
                $inner->whereNull('subscription_expires_at')
                    ->orWhere('subscription_expires_at', '>', now());
            });
    }
}
