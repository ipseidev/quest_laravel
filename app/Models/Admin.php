<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A panel operator. See the `create_admins_table` migration for why this is not
 * a flag on `User`.
 *
 * Note what this model does *not* have: no global scopes, no relationship to any
 * journal content, and no Sanctum tokens. It exists only to satisfy the `admin`
 * session guard.
 */
class Admin extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Belt to the guard's braces. The `admin` guard already resolves only against
     * this table, so a `User` can never arrive here — but Filament calls this on
     * every request and an explicit answer costs nothing.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin';
    }
}
