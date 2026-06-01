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
            'lastPullTimestamp' => ['nullable', 'string', 'date'],
        ];
    }
}
