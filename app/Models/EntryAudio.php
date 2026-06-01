<?php

namespace App\Models;

use App\Models\Scopes\ThroughEntryToCurrentUserScope;
use Database\Factories\EntryAudioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'entry_id',
    'uri',
    'remote_uri',
    'duration_ms',
    'waveform',
    'is_deleted',
])]
class EntryAudio extends Model
{
    /** @use HasFactory<EntryAudioFactory> */
    use HasFactory;

    protected $table = 'entry_audio';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected static function booted(): void
    {
        static::addGlobalScope(new ThroughEntryToCurrentUserScope);
    }

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
            'waveform' => 'array',
            'is_deleted' => 'boolean',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
