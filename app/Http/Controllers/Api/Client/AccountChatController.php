<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Chat\ChatRoomService;
use Pterodactyl\Services\Security\NodeSecureModeService;
use Pterodactyl\Services\Security\SecurityEventService;
use Pterodactyl\Http\Requests\Api\Client\Account\Chat\GetGlobalChatMessagesRequest;
use Pterodactyl\Http\Requests\Api\Client\Account\Chat\StoreGlobalChatMessageRequest;
use Pterodactyl\Http\Requests\Api\Client\Account\Chat\UploadGlobalChatMediaRequest;

class AccountChatController extends ClientApiController
{
    private const CHAT_MEDIA_MIME_ALLOWLIST = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/webm',
        'video/quicktime',
    ];

    public function __construct(
        private ChatRoomService $chatRoomService,
        private NodeSecureModeService $nodeSecureModeService,
    )
    {
        parent::__construct();
    }

    public function index(GetGlobalChatMessagesRequest $request): array
    {
        $limit = (int) $request->input('limit', 100);

        $this->chatRoomService->markRoomRead(ChatMessage::ROOM_GLOBAL, null, $request->user()->id, $limit);
        $messages = $this->chatRoomService->listMessages(ChatMessage::ROOM_GLOBAL, null, $limit);

        return [
            'object' => 'list',
            'data' => array_map(fn (array $row) => ['object' => ChatMessage::RESOURCE_NAME, 'attributes' => $row], $messages),
        ];
    }

    public function store(StoreGlobalChatMessageRequest $request): JsonResponse
    {
        if ($blocked = $this->chatWriteBlockedResponse($request, null)) {
            return $blocked;
        }

        $body = filled($request->input('body')) ? (string) $request->input('body') : null;
        $mediaUrl = filled($request->input('media_url')) ? (string) $request->input('media_url') : null;
        if ($blocked = $this->chatSecretBlockedResponse($request, $body, null)) {
            return $blocked;
        }
        if ($blocked = $this->chatSecretBlockedResponse($request, $mediaUrl, null)) {
            return $blocked;
        }

        $replyToId = $request->integer('reply_to_id');
        if ($replyToId) {
            $reply = ChatMessage::query()->find($replyToId);
            if (!$reply || $reply->room_type !== ChatMessage::ROOM_GLOBAL || !is_null($reply->room_id)) {
                return response()->json([
                    'errors' => [[
                        'code' => 'BadRequestHttpException',
                        'status' => '400',
                        'detail' => 'The selected reply_to_id is invalid for global room.',
                    ]],
                ], 400);
            }
        }

        $message = $this->chatRoomService->storeMessage(
            ChatMessage::ROOM_GLOBAL,
            null,
            $request->user()->id,
            $body,
            $mediaUrl,
            $replyToId ?: null,
        );

        $messages = $this->chatRoomService->listMessages(ChatMessage::ROOM_GLOBAL, null, 1);
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

    public function upload(UploadGlobalChatMediaRequest $request): JsonResponse
    {
        if ($blocked = $this->chatWriteBlockedResponse($request, null)) {
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
        if ($blocked = $this->chatUploadBlockedResponse($request, $media, null)) {
            return $blocked;
        }

        $extension = strtolower($media->getClientOriginalExtension() ?: 'bin');
        $filename = sprintf('%d_%s.%s', time(), bin2hex(random_bytes(6)), $extension);
        $path = sprintf('chat/global/%s', $filename);

        Storage::disk('public')->putFileAs('chat/global', $media, $filename);

        return response()->json([
            'object' => 'chat_upload',
            'attributes' => [
                'url' => asset('storage/' . $path),
                'path' => $path,
            ],
        ], 201);
    }

    private function chatUploadBlockedResponse(Request $request, UploadedFile $media, ?int $serverId): ?JsonResponse
    {
        $originalName = (string) $media->getClientOriginalName();
        if (preg_match('/[\x00-\x1F]|\.{2,}|[\/\\\\]/', $originalName) === 1) {
            return $this->rejectChatUpload($request, $serverId, 'invalid_original_filename');
        }

        $mime = strtolower((string) ($media->getMimeType() ?: ''));
        if (!in_array($mime, self::CHAT_MEDIA_MIME_ALLOWLIST, true)) {
            return $this->rejectChatUpload($request, $serverId, 'mime_not_allowed:' . $mime);
        }

        $head = @file_get_contents((string) $media->getRealPath(), false, null, 0, 4096);
        if (is_string($head) && $head !== '') {
            $lower = strtolower($head);
            if (
                str_contains($lower, '<?php')
                || str_contains($lower, '<script')
                || str_contains($lower, '#!/bin/')
                || str_starts_with($head, "MZ")
                || str_starts_with($head, "\x7FELF")
            ) {
                return $this->rejectChatUpload($request, $serverId, 'suspicious_binary_signature');
            }
        }

        return null;
    }

    private function rejectChatUpload(Request $request, ?int $serverId, string $reason): JsonResponse
    {
        app(SecurityEventService::class)->log('security:chat.upload_blocked', [
            'actor_user_id' => $request->user()?->id,
            'server_id' => $serverId,
            'ip' => $request->ip(),
            'risk_level' => 'high',
            'meta' => [
                'room' => 'global',
                'path' => '/' . ltrim((string) $request->path(), '/'),
                'method' => strtoupper((string) $request->method()),
                'reason' => $reason,
            ],
        ]);

        return response()->json([
            'errors' => [[
                'code' => 'BadRequestHttpException',
                'status' => '400',
                'detail' => 'Uploaded media rejected by security policy.',
            ]],
        ], 400);
    }

    private function chatWriteBlockedResponse(Request $request, ?int $serverId): ?JsonResponse
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
                'room' => 'global',
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

    private function chatSecretBlockedResponse(Request $request, ?string &$body, ?int $serverId): ?JsonResponse
    {
        $analysis = $this->nodeSecureModeService->inspectChatMessage(
            $body,
            $serverId,
            $request->user()->id,
            (string) $request->ip()
        );

        if (!empty($analysis['allowed'])) {
            $body = $analysis['value'] ?? $body;

            return null;
        }

        return response()->json([
            'errors' => [[
                'code' => 'SecretLeakDetected',
                'status' => '422',
                'detail' => 'Sensitive token pattern detected in chat message. Remove secret data and retry.',
                'meta' => [
                    'findings' => $analysis['findings'] ?? [],
                    'quarantined' => (bool) ($analysis['quarantined'] ?? false),
                ],
            ]],
        ], 422);
    }
}
