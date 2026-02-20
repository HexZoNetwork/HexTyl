<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Chat\ChatRoomService;
use Pterodactyl\Services\Security\SecurityEventService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Chat\GetServerChatMessagesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Chat\StoreServerChatMessageRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Chat\UploadServerChatMediaRequest;

class ChatController extends ClientApiController
{
    public function __construct(private ChatRoomService $chatRoomService)
    {
        parent::__construct();
    }

    public function index(GetServerChatMessagesRequest $request, Server $server): array
    {
        $limit = (int) $request->input('limit', 100);

        $this->chatRoomService->markRoomRead(ChatMessage::ROOM_SERVER, $server->id, $request->user()->id, $limit);
        $messages = $this->chatRoomService->listMessages(ChatMessage::ROOM_SERVER, $server->id, $limit);

        return [
            'object' => 'list',
            'data' => array_map(fn (array $row) => ['object' => ChatMessage::RESOURCE_NAME, 'attributes' => $row], $messages),
        ];
    }

    public function store(StoreServerChatMessageRequest $request, Server $server): JsonResponse
    {
        if ($blocked = $this->chatWriteBlockedResponse($request, $server->id)) {
            return $blocked;
        }

        $replyToId = $request->integer('reply_to_id');
        if ($replyToId) {
            $reply = ChatMessage::query()->find($replyToId);
            if (!$reply || $reply->room_type !== ChatMessage::ROOM_SERVER || (int) $reply->room_id !== $server->id) {
                return response()->json([
                    'errors' => [[
                        'code' => 'BadRequestHttpException',
                        'status' => '400',
                        'detail' => 'The selected reply_to_id is invalid for this server room.',
                    ]],
                ], 400);
            }
        }

        $message = $this->chatRoomService->storeMessage(
            ChatMessage::ROOM_SERVER,
            $server->id,
            $request->user()->id,
            filled($request->input('body')) ? (string) $request->input('body') : null,
            filled($request->input('media_url')) ? (string) $request->input('media_url') : null,
            $replyToId ?: null,
        );

        $messages = $this->chatRoomService->listMessages(ChatMessage::ROOM_SERVER, $server->id, 1);
        $payload = $messages[0] ?? [
            'id' => $message->id,
            'sender_uuid' => $request->user()->uuid,
            'sender_email' => $request->user()->email,
            'body' => $message->body,
            'media_url' => $message->media_url,
            'reply_to_id' => $message->reply_to_id,
            'reply_preview' => null,
            'delivered_count' => 0,
            'read_count' => 0,
            'created_at' => $message->created_at?->toAtomString(),
        ];

        return response()->json([
            'object' => ChatMessage::RESOURCE_NAME,
            'attributes' => $payload,
        ], 201);
    }

    public function upload(UploadServerChatMediaRequest $request, Server $server): JsonResponse
    {
        if ($blocked = $this->chatWriteBlockedResponse($request, $server->id)) {
            return $blocked;
        }

        /** @var UploadedFile|null $media */
        $media = $request->file('media') ?: $request->file('image');
        if (!$media) {
            return response()->json([
                'errors' => [[
                    'code' => 'BadRequestHttpException',
                    'status' => '400',
                    'detail' => 'No media file was uploaded.',
                ]],
            ], 400);
        }

        $extension = strtolower($media->getClientOriginalExtension() ?: 'bin');
        $filename = sprintf('%d_%s.%s', time(), bin2hex(random_bytes(6)), $extension);
        $path = sprintf('chat/server/%d/%s', $server->id, $filename);

        Storage::disk('public')->putFileAs(sprintf('chat/server/%d', $server->id), $media, $filename);

        return response()->json([
            'object' => 'chat_upload',
            'attributes' => [
                'url' => asset('storage/' . $path),
                'path' => $path,
            ],
        ], 201);
    }

    private function chatWriteBlockedResponse(Request $request, int $serverId): ?JsonResponse
    {
        $incidentMode = filter_var(
            (string) Cache::remember('system:chat_incident_mode', 30, function () {
                return (string) (DB::table('system_settings')->where('key', 'chat_incident_mode')->value('value') ?? 'false');
            }),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$incidentMode || $request->user()->isRoot()) {
            return null;
        }

        app(SecurityEventService::class)->log('security:chat.incident_mode_block', [
            'actor_user_id' => $request->user()->id,
            'server_id' => $serverId,
            'ip' => $request->ip(),
            'risk_level' => 'medium',
            'meta' => [
                'room' => 'server',
                'path' => '/' . ltrim((string) $request->path(), '/'),
                'method' => strtoupper((string) $request->method()),
            ],
        ]);

        return response()->json([
            'errors' => [[
                'code' => 'LockedHttpException',
                'status' => '423',
                'detail' => 'Chat write is temporarily disabled by incident mode.',
            ]],
        ], 423);
    }
}
