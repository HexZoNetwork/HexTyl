<?php

namespace Pterodactyl\Http\Requests\Api\Client\Account\Chat;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UploadGlobalChatMediaRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'image' => 'required|file|image|max:8192|mimes:jpg,jpeg,png,gif,webp,svg',
        ];
    }
}
