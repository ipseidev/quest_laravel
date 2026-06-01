<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppleAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identityToken' => ['required', 'string'],
            'authorizationCode' => ['nullable', 'string'],
            'fullName' => ['nullable', 'array'],
            'fullName.givenName' => ['nullable', 'string'],
            'fullName.familyName' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'deviceId' => ['required', 'uuid'],
        ];
    }
}
