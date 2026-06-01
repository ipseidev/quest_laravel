<?php

namespace App\Http\Resources;

use App\Models\EntryAttachment;
use App\Support\IsoDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryAttachmentResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return self::serialize($this->resource);
    }

    public static function serialize(EntryAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'entryId' => $attachment->entry_id,
            'uri' => '',
            'remoteUri' => $attachment->remote_uri,
            'width' => (int) $attachment->width,
            'height' => (int) $attachment->height,
            'isDeleted' => (bool) $attachment->is_deleted,
            'createdAt' => IsoDate::format($attachment->created_at),
            'updatedAt' => IsoDate::format($attachment->updated_at),
            'syncedAt' => null,
        ];
    }
}
