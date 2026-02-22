<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers;

use Illuminate\Validation\Validator;
use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class SendCommandRequest extends ClientApiRequest
{
    /**
     * Determine if the API user has permission to perform this action.
     */
    public function permission(): string
    {
        return Permission::ACTION_CONTROL_CONSOLE;
    }

    /**
     * Rules to validate this request against.
     */
    public function rules(): array
    {
        return [
            'command' => 'required|string|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $command = (string) $this->input('command', '');
            if ($command === '') {
                return;
            }

            foreach ($this->blockedCommandPatterns() as $pattern) {
                if (preg_match($pattern, $command) === 1) {
                    $validator->errors()->add('command', 'Command blocked by security policy.');

                    return;
                }
            }

            if ($this->isCloudInitExfiltrationProbe($command)) {
                $validator->errors()->add('command', 'Command blocked by cloud-init exfiltration guard.');
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function blockedCommandPatterns(): array
    {
        return [
            '/169\.254\.169\.254(?:[:\/]|$)/i',
            '/fd00:ec2::254(?:[:\/]|$)/i',
            '#/(?:latest/user-data|metadata/v1/user-data|openstack/latest/user_data)\b#i',
            '#/var/lib/cloud/(?:instance|instances/[^\s/]+)/user-data(?:\.txt)?#i',
            '#/mnt/(?:host_var|host_cloud|host_root)\b#i',
            '#/user-data(?:\.txt)?\b#i',
            '#\bcloud-config\b#i',
        ];
    }

    private function isCloudInitExfiltrationProbe(string $command): bool
    {
        $normalized = strtolower($command);

        $hasSensitiveTarget = str_contains($normalized, 'user-data')
            || str_contains($normalized, 'cloud-init')
            || str_contains($normalized, '/var/lib/cloud')
            || str_contains($normalized, '/mnt/host_')
            || str_contains($normalized, '169.254.169.254')
            || str_contains($normalized, 'fd00:ec2::254');

        if (!$hasSensitiveTarget) {
            return false;
        }

        if (preg_match('/\bfind\s+\/(?:var|mnt)\b.{0,220}\buser-data\*?/is', $normalized) === 1) {
            return true;
        }

        foreach ([
            'readfilesync',
            'existssync',
            'child_process',
            'exec(',
            'cat ',
        ] as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }
}
