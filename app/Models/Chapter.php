<?php

namespace App\Models;

use App\Models\Scopes\BelongsToCurrentUserScope;
use Database\Factories\ChapterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'kind',
    'period_start',
    'period_end',
    'quest_id',
    'register',
    'title',
    'body',
    'threads',
    'status',
])]
class Chapter extends Model
{
    /** @use HasFactory<ChapterFactory> */
    use HasFactory, HasUuids;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected static function booted(): void
    {
        static::addGlobalScope(new BelongsToCurrentUserScope);
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    protected function casts(): array
    {
        return [
            'title' => 'encrypted',
            'body' => 'encrypted:array',
            'threads' => 'encrypted:array',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }
}
