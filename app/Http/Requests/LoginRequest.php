<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'deviceId' => ['required', 'uuid'],
            'platform' => ['sometimes', 'nullable', 'string', 'max:16'],
            'appVersion' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
