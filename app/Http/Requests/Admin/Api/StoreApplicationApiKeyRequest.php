<?php

namespace Pterodactyl\Http\Requests\Admin\Api;

use Illuminate\Validation\Rule;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class StoreApplicationApiKeyRequest extends AdminFormRequest
{
    /**
     * @throws \ReflectionException
     * @throws \ReflectionException
     */
    public function rules(): array
    {
        $modelRules = ApiKey::getRules();
        $user = $this->user();
        $allowedScopes = $user ? AdminAcl::getAssignableCreationScopes($user) : [];

        return [
            'memo' => $modelRules['memo'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'string', Rule::in($allowedScopes)],
        ];
    }

    public function attributes(): array
    {
        return [
            'memo' => 'Description',
            'scopes' => 'Scopes',
            'scopes.*' => 'Scope',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getRequestedScopes(): array
    {
        return collect((array) $this->validated('scopes', []))
            ->map(fn ($scope) => trim((string) $scope))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
