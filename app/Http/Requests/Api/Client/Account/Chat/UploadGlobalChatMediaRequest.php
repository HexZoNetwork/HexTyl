<?php

namespace Pterodactyl\Http\Requests\Api\Client\Account\Chat;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UploadGlobalChatMediaRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'media' => 'required_without:image|file|max:51200|mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov,m4v|mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime',
            'image' => 'required_without:media|file|max:51200|mimes:jpg,jpeg,png,gif,webp|mimetypes:image/jpeg,image/png,image/gif,image/webp',
        ];
    }
}
