<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deviceId' => ['required', 'uuid'],
            'changes' => ['present', 'array'],
            'changes.*' => ['array'],
            'changes.*.entityType' => ['required', 'in:entry,quest,character,quote,entry_quest,entry_character,entry_attachment,entry_audio'],
            'changes.*.entityId' => ['required', 'string'],
            'changes.*.operation' => ['required', 'in:create,update,delete'],
            'changes.*.data' => ['required', 'array'],
        ];
    }
}
