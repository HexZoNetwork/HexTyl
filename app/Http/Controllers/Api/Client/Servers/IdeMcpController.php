<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Server;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Ide\ProxyMcpRequest;
use Pterodactyl\Services\Ide\McpGatewayService;
use RuntimeException;
use Throwable;

class IdeMcpController extends ClientApiController
{
    public function __construct(private McpGatewayService $mcpGatewayService)
    {
        parent::__construct();
    }

    public function proxy(ProxyMcpRequest $request, Server $server): JsonResponse
    {
        try {
            $result = $this->mcpGatewayService->proxy(
                $request->validated(),
                $server,
                $request->user(),
                (string) $request->ip()
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'errors' => [[
                    'code' => 'BadRequestHttpException',
                    'status' => '422',
                    'detail' => $exception->getMessage(),
                ]],
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'errors' => [[
                    'code' => 'HttpException',
                    'status' => '502',
                    'detail' => 'MCP gateway request failed.',
                ]],
            ], 502);
        }

        return response()->json([
            'object' => 'mcp_response',
            'attributes' => $result,
        ]);
    }
}
