<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Chat;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UploadServerChatMediaRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_CHAT_CREATE;
    }

    public function rules(): array
    {
        return [
            'image' => 'required|file|image|max:8192|mimes:jpg,jpeg,png,gif,webp,svg',
        ];
    }
}
