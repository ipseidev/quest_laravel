<?php

namespace App\Http\Resources;

use App\Support\IsoDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'createdAt' => IsoDate::format($this->created_at),
            'aiChaptersOptIn' => (bool) $this->ai_chapters_opt_in,
        ];
    }
}
