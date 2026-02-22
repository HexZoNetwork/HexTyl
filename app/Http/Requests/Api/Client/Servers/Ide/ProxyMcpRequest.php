<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Ide;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class ProxyMcpRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_IDE_CONNECT;
    }

    public function rules(): array
    {
        return [
            'provider' => 'nullable|string|in:routeway,openai,claude',
            'model' => 'nullable|string|max:140',
            'quality_profile' => 'nullable|string|in:fast,balanced,premium',
            'task_type' => 'nullable|string|in:coding,general',
            'fallback' => 'nullable|boolean',
            'messages' => 'required|array|min:1|max:60',
            'messages.*.role' => 'required|string|in:system,user,assistant',
            'messages.*.content' => 'required|string|max:16000',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1|max:32768',
            'api_key' => 'nullable|string|min:10|max:300',
        ];
    }
}
