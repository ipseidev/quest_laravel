<?php

namespace App\Http\Resources;

use App\Models\Quest;
use App\Support\IsoDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return self::serialize($this->resource);
    }

    public static function serialize(Quest $quest): array
    {
        return [
            'id' => $quest->id,
            'type' => $quest->type,
            'title' => $quest->title,
            'description' => $quest->description,
            'status' => $quest->status,
            'color' => $quest->color,
            'icon' => $quest->icon,
            'startedAt' => IsoDate::format($quest->started_at),
            'completedAt' => IsoDate::format($quest->completed_at),
            'isDeleted' => (bool) $quest->is_deleted,
            'createdAt' => IsoDate::format($quest->created_at),
            'updatedAt' => IsoDate::format($quest->updated_at),
            'syncedAt' => null,
        ];
    }
}
