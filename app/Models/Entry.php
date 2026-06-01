<?php

namespace App\Models;

use App\Models\Scopes\BelongsToCurrentUserScope;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id',
    'user_id',
    'title',
    'html',
    'mood',
    'latitude',
    'longitude',
    'entry_date',
    'is_deleted',
])]
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
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
            'title' => 'encrypted',
            'html' => 'encrypted',
            'latitude' => 'float',
            'longitude' => 'float',
            'entry_date' => 'datetime',
            'is_deleted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quests(): BelongsToMany
    {
        return $this->belongsToMany(Quest::class, 'entry_quests')
            ->withPivot('created_at');
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'entry_characters')
            ->withPivot('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EntryAttachment::class);
    }

    public function audio(): HasMany
    {
        return $this->hasMany(EntryAudio::class);
    }
}
