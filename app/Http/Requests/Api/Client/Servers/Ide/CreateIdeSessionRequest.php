<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Ide;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class CreateIdeSessionRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_IDE_CONNECT;
    }

    public function rules(): array
    {
        return [
            'terminal' => 'nullable|boolean',
            'extensions' => 'nullable|boolean',
        ];
    }
}
