<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Chat\ChatRoomService;
use Pterodactyl\Http\Requests\Api\Client\Account\Chat\GetGlobalChatMessagesRequest;
use Pterodactyl\Http\Requests\Api\Client\Account\Chat\StoreGlobalChatMessageRequest;
use Pterodactyl\Http\Requests\Api\Client\Account\Chat\UploadGlobalChatMediaRequest;

class AccountChatController extends ClientApiController
{
    public function __construct(private ChatRoomService $chatRoomService)
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
            filled($request->input('body')) ? (string) $request->input('body') : null,
            filled($request->input('media_url')) ? (string) $request->input('media_url') : null,
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
}
