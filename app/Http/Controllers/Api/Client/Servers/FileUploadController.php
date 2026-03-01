<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\UploadFileRequest;

class FileUploadController extends ClientApiController
{
    /**
     * FileUploadController constructor.
     */
    public function __construct(
        private NodeJWTService $jwtService,
    ) {
        parent::__construct();
    }

    /**
     * Returns an url where files can be uploaded to.
     */
    public function __invoke(UploadFileRequest $request, Server $server): JsonResponse
    {
        return new JsonResponse([
            'object' => 'signed_url',
            'attributes' => [
                'url' => $this->getUploadUrl($server, $request->user()),
            ],
        ]);
    }

    /**
     * Returns an url where files can be uploaded to.
     */
    protected function getUploadUrl(Server $server, User $user): string
    {
        $token = $this->jwtService
            ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
            ->setUser($user)
            ->setClaims(['server_uuid' => $server->uuid])
            ->handle($server->node, $user->id . $server->uuid);

        return sprintf('%s/upload/file?token=%s', $this->wingsPublicBase($server), $token->toString());
    }

    private function wingsPublicBase(Server $server): string
    {
        $proxySocketBase = trim((string) config('wings_security.socket_proxy_url', ''));
        if ($proxySocketBase !== '') {
            return rtrim($proxySocketBase, '/');
        }

        $appUrl = rtrim((string) config('app.url', ''), '/');
        if ($appUrl !== '' && preg_match('#^https?://#i', $appUrl) === 1) {
            return $appUrl . '/wings';
        }

        return $server->node->getConnectionAddress();
    }
}
