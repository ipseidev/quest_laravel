<?php

namespace App\Http\Resources;

use App\Models\Quote;
use App\Support\IsoDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return self::serialize($this->resource);
    }

    public static function serialize(Quote $quote): array
    {
        return [
            'id' => $quote->id,
            'text' => $quote->text,
            'source' => $quote->source,
            'note' => $quote->note,
            'isDeleted' => (bool) $quote->is_deleted,
            'createdAt' => IsoDate::format($quote->created_at),
            'updatedAt' => IsoDate::format($quote->updated_at),
            'syncedAt' => null,
        ];
    }
}
