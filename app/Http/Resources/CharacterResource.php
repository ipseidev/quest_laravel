<?php

namespace App\Http\Resources;

use App\Models\Character;
use App\Support\IsoDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return self::serialize($this->resource);
    }

    public static function serialize(Character $character): array
    {
        return [
            'id' => $character->id,
            'name' => $character->name,
            'relationship' => $character->relationship,
            'note' => $character->note,
            'photoUri' => '',
            'remotePhotoUri' => $character->remote_photo_uri,
            'color' => $character->color,
            'isDeleted' => (bool) $character->is_deleted,
            'createdAt' => IsoDate::format($character->created_at),
            'updatedAt' => IsoDate::format($character->updated_at),
            'syncedAt' => null,
        ];
    }
}
