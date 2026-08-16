<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPullRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deviceId' => ['required', 'uuid'],
            'platform' => ['sometimes', 'nullable', 'string', 'max:16'],
            'appVersion' => ['sometimes', 'nullable', 'string', 'max:32'],
            'lastPullTimestamp' => ['nullable', 'string', 'date'],
        ];
    }
}
