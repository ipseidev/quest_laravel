<?php

namespace App\Models;

use App\Models\Scopes\BelongsToCurrentUserScope;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'id',
    'user_id',
    'name',
    'relationship',
    'note',
    'photo_uri',
    'remote_photo_uri',
    'color',
    'is_deleted',
])]
class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToCurrentUserScope);
    }

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'relationship' => 'encrypted',
            'note' => 'encrypted',
            'is_deleted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'entry_characters')
            ->withPivot('created_at');
    }
}
