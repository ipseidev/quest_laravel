<?php

namespace App\Http\Resources;

use App\Models\EntryAudio;
use App\Support\IsoDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryAudioResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return self::serialize($this->resource);
    }

    public static function serialize(EntryAudio $audio): array
    {
        return [
            'id' => $audio->id,
            'entryId' => $audio->entry_id,
            'uri' => '',
            'remoteUri' => $audio->remote_uri,
            'durationMs' => (int) $audio->duration_ms,
            'waveform' => $audio->waveform ?? [],
            'isDeleted' => (bool) $audio->is_deleted,
            'createdAt' => IsoDate::format($audio->created_at),
            'updatedAt' => IsoDate::format($audio->updated_at),
            'syncedAt' => null,
        ];
    }
}
