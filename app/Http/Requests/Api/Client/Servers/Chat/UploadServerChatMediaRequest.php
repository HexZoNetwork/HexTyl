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
            'media' => 'required_without:image|file|max:51200|mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov,m4v',
            'image' => 'required_without:media|file|max:51200|mimes:jpg,jpeg,png,gif,webp',
        ];
    }
}
