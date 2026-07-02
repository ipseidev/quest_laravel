<?php

namespace App\Http\Resources;

use App\Models\Chapter;
use App\Support\IsoDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChapterResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return self::serialize($this->resource);
    }

    public static function serialize(Chapter $chapter): array
    {
        return [
            'id' => $chapter->id,
            'kind' => $chapter->kind,
            'periodStart' => IsoDate::format($chapter->period_start),
            'periodEnd' => IsoDate::format($chapter->period_end),
            'questId' => $chapter->quest_id,
            'register' => $chapter->register,
            'title' => $chapter->title,
            'paragraphs' => $chapter->body['paragraphs'] ?? [],
            'threads' => $chapter->threads ?? [],
            'status' => $chapter->status,
            'generatedAt' => IsoDate::format($chapter->created_at),
        ];
    }
}
