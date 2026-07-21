<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Entitlement (paid + consent) is enforced in the controller; auth is
        // guaranteed by the route's auth:sanctum middleware.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'context' => ['required', 'array'],
            'context.type' => ['required', Rule::in(['quest', 'character', 'general'])],
            // Required for entity-scoped chats, absent for a general chat.
            'context.entityId' => [
                'nullable',
                'uuid',
                Rule::requiredIf(fn () => in_array($this->input('context.type'), ['quest', 'character'], true)),
            ],
            'messages' => ['required', 'array', 'min:1', 'max:50'],
            'messages.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'messages.*.content' => ['required', 'string', 'max:8000'],
        ];
    }
}
