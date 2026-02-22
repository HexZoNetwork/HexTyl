<?php

namespace Pterodactyl\Http\Requests\Api\Client\Account\Chat;

use Illuminate\Validation\Validator;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class StoreGlobalChatMessageRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'body' => 'nullable|string|max:8000',
            'media_url' => ['nullable', 'url', 'max:2048', 'regex:/^https?:\\/\\//i'],
            'reply_to_id' => 'nullable|integer|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!filled($this->input('body')) && !filled($this->input('media_url'))) {
                $validator->errors()->add('body', 'Either body or media_url must be provided.');
            }

            $mediaUrl = filled($this->input('media_url')) ? (string) $this->input('media_url') : null;
            if (!$mediaUrl) {
                return;
            }

            $parts = parse_url($mediaUrl);
            $host = strtolower((string) ($parts['host'] ?? ''));
            if ($host === '') {
                $validator->errors()->add('media_url', 'Invalid media_url host.');

                return;
            }

            if (isset($parts['user']) || isset($parts['pass'])) {
                $validator->errors()->add('media_url', 'Credentialed media_url is not allowed.');

                return;
            }

            if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
                $validator->errors()->add('media_url', 'Local network media_url is not allowed.');

                return;
            }

            if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                $publicIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                if ($publicIp === false) {
                    $validator->errors()->add('media_url', 'Private or reserved IP media_url is not allowed.');
                }
            }
        });
    }
}
