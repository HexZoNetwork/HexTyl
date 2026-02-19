<?php

namespace Pterodactyl\Http\Requests\Api\Client\Account\Chat;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class GetGlobalChatMessagesRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'limit' => 'sometimes|integer|min:1|max:200',
        ];
    }
}
