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
            /*
             * The language the AI layer writes in, as the server has it. Null means the
             * client has never pushed one — exposed rather than defaulted so the app can
             * tell "never set" from "deliberately fr" and push once instead of on every
             * launch. Generation itself falls back via User::chapterLocale().
             */
            'locale' => $this->locale,
            /*
             * The server's view of the paid entitlement. Additive to the V1 spec, and
             * worth it: the app reads its entitlement from RevenueCat directly, so when
             * the webhook has not landed the two disagree and the only symptom is a
             * paying user getting 402 media_quota_exceeded on an upload. Exposing what
             * the server believes turns that into something a support reply can name.
             */
            'plus' => $this->hasActiveSubscription(),
            'plusExpiresAt' => IsoDate::format($this->subscription_expires_at),
        ];
    }
}
