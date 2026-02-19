<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Chat;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class GetServerChatMessagesRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_CHAT_READ;
    }

    public function rules(): array
    {
        return [
            'limit' => 'sometimes|integer|min:1|max:200',
        ];
    }
}
