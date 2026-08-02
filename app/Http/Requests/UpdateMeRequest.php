<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Both fields are individually optional so the client can push either one alone —
     * a language change must not force it to restate the AI consent it may not have
     * loaded yet. The paired `required_without` keeps an empty body a 422, which is
     * the documented behavior for this endpoint.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'aiChaptersOptIn' => ['required_without:locale', 'boolean'],
            'locale' => ['required_without:aiChaptersOptIn', 'string', Rule::in(User::SUPPORTED_LOCALES)],
        ];
    }
}
