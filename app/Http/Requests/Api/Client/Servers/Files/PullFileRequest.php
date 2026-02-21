<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Files;

use Closure;
use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Pterodactyl\Services\Security\OutboundTargetGuardService;

class PullFileRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_CREATE;
    }

    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'url',
                function (string $attribute, mixed $value, Closure $fail) {
                    $check = app(OutboundTargetGuardService::class)->inspect((string) $value);
                    if (($check['ok'] ?? false) !== true) {
                        $fail((string) ($check['reason'] ?? 'Invalid outbound target URL.'));
                    }
                },
            ],
            'directory' => 'nullable|string',
            'filename' => 'nullable|string',
            'use_header' => 'boolean',
            'foreground' => 'boolean',
        ];
    }
}
