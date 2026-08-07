<?php

namespace App\Http\Resources;

use App\Models\EntryVideo;
use App\Support\IsoDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryVideoResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return self::serialize($this->resource);
    }

    public static function serialize(EntryVideo $video): array
    {
        return [
            'id' => $video->id,
            'entryId' => $video->entry_id,
            // Always '': `uri` is the origin device's local path, which is
            // meaningless anywhere else. Clients read `remoteUri` and cache the
            // binary under a path of their own.
            'uri' => '',
            'remoteUri' => $video->remote_uri,
            'durationMs' => (int) $video->duration_ms,
            'width' => (int) $video->width,
            'height' => (int) $video->height,
            'isDeleted' => (bool) $video->is_deleted,
            'createdAt' => IsoDate::format($video->created_at),
            'updatedAt' => IsoDate::format($video->updated_at),
            'syncedAt' => null,
        ];
    }
}
