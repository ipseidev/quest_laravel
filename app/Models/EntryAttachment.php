<?php

namespace App\Models;

use App\Models\Scopes\ThroughEntryToCurrentUserScope;
use Database\Factories\EntryAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'entry_id',
    'uri',
    'remote_uri',
    'width',
    'height',
    'is_deleted',
])]
class EntryAttachment extends Model
{
    /** @use HasFactory<EntryAttachmentFactory> */
    use HasFactory;

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
            'width' => 'integer',
            'height' => 'integer',
            'is_deleted' => 'boolean',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
