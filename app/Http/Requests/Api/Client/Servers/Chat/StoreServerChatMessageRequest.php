<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Chat;

use Illuminate\Validation\Validator;
use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class StoreServerChatMessageRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_CHAT_CREATE;
    }

    public function rules(): array
    {
        return [
            'body' => 'nullable|string|max:8000',
            'media_url' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\\/\\//i'],
            'reply_to_id' => 'nullable|integer|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!filled($this->input('body')) && !filled($this->input('media_url'))) {
                $validator->errors()->add('body', 'Either body or media_url must be provided.');
            }
        });
    }
}
