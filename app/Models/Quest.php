<?php

namespace App\Models;

use App\Models\Scopes\BelongsToCurrentUserScope;
use Database\Factories\QuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'id',
    'user_id',
    'type',
    'title',
    'description',
    'status',
    'color',
    'icon',
    'started_at',
    'completed_at',
    'is_deleted',
])]
class Quest extends Model
{
    /** @use HasFactory<QuestFactory> */
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
            'description' => 'encrypted',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_deleted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'entry_quests')
            ->withPivot('created_at');
    }
}
