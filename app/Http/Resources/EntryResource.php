<?php

namespace App\Http\Resources;

use App\Models\Entry;
use App\Support\IsoDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return self::serialize($this->resource);
    }

    public static function serialize(Entry $entry): array
    {
        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'html' => $entry->html,
            'mood' => $entry->mood,
            'latitude' => $entry->latitude,
            'longitude' => $entry->longitude,
            'entryDate' => IsoDate::format($entry->entry_date),
            'isDeleted' => (bool) $entry->is_deleted,
            'createdAt' => IsoDate::format($entry->created_at),
            'updatedAt' => IsoDate::format($entry->updated_at),
            'syncedAt' => null,
        ];
    }
}
