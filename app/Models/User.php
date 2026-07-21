<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
     * TODO(billing): no subscription/entitlement mechanism exists server-side yet
     * (no Cashier/Stripe/RevenueCat, no column). Until one lands, treat every
     * account as entitled so consenting users can exercise AI in dev/test. When
     * billing ships, implement the real check HERE — chat, the interviewer, and
     * (retrofit) chapters all inherit it through hasAiAccess().
     */
    public function hasActiveSubscription(): bool
    {
        return true;
    }
}
