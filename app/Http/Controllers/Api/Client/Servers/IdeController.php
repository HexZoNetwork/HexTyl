<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Ide\CreateIdeSessionRequest;
use Pterodactyl\Services\Ide\IdeSessionService;
use RuntimeException;

class IdeController extends ClientApiController
{
    public function __construct(private IdeSessionService $ideSessionService)
    {
        parent::__construct();
    }

    public function createSession(CreateIdeSessionRequest $request, Server $server): JsonResponse
    {
        try {
            $session = $this->ideSessionService->createSession(
                $server,
                $request->user(),
                (string) $request->ip(),
                [
                    'terminal' => $request->boolean('terminal'),
                    'extensions' => $request->boolean('extensions'),
                ]
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'errors' => [[
                    'code' => 'BadRequestHttpException',
                    'status' => '422',
                    'detail' => $exception->getMessage(),
                ]],
            ], 422);
        }

        return response()->json([
            'object' => 'ide_session',
            'attributes' => $session,
        ], 201);
    }
}
