<?php

namespace Pterodactyl\Services\Chat;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Pterodactyl\Models\ChatMessage;

class ChatRoomService
{
    private const CACHE_TTL_SECONDS = 20;

    public function listMessages(string $roomType, ?int $roomId, int $limit = 100): array
    {
        $cacheKey = $this->cacheKey($roomType, $roomId, $limit);
        $cached = Redis::get($cacheKey);

        if (is_string($cached)) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $messages = ChatMessage::query()
            ->with(['sender:id,uuid,email', 'replyTo:id,body,media_url'])
            ->withCount([
                'receipts as delivered_count' => function ($query) {
                    $query->whereNotNull('delivered_at')
                        ->whereColumn('chat_message_receipts.user_id', '!=', 'chat_messages.user_id');
                },
                'receipts as read_count' => function ($query) {
                    $query->whereNotNull('read_at')
                        ->whereColumn('chat_message_receipts.user_id', '!=', 'chat_messages.user_id');
                },
            ])
            ->where('room_type', $roomType)
            ->where('room_id', $roomId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(function (ChatMessage $message) {
                return [
                    'id' => $message->id,
                    'sender_uuid' => $message->sender?->uuid,
                    'sender_email' => $message->sender?->email,
                    'body' => $message->body,
                    'media_url' => $message->media_url,
                    'reply_to_id' => $message->reply_to_id,
                    'reply_preview' => $message->replyTo?->body ?: $message->replyTo?->media_url,
                    'delivered_count' => (int) $message->delivered_count,
                    'read_count' => (int) $message->read_count,
                    'created_at' => $message->created_at?->toAtomString(),
                ];
            })
            ->all();

        Redis::setex($cacheKey, self::CACHE_TTL_SECONDS, json_encode($messages, JSON_UNESCAPED_UNICODE));

        return $messages;
    }

    public function storeMessage(
        string $roomType,
        ?int $roomId,
        int $userId,
        ?string $body,
        ?string $mediaUrl,
        ?int $replyToId = null
    ): ChatMessage {
        $message = ChatMessage::query()->create([
            'room_type' => $roomType,
            'room_id' => $roomId,
            'user_id' => $userId,
            'body' => $body,
            'media_url' => $mediaUrl,
            'reply_to_id' => $replyToId,
        ]);

        $this->forgetRoomCache($roomType, $roomId);

        Redis::publish('chat:events', json_encode([
            'event' => 'message.created',
            'room_type' => $roomType,
            'room_id' => $roomId,
            'message_id' => $message->id,
        ], JSON_UNESCAPED_UNICODE));

        return $message;
    }

    public function markRoomRead(string $roomType, ?int $roomId, int $userId, int $limit = 100): bool
    {
        $messageIds = ChatMessage::query()
            ->select('chat_messages.id')
            ->leftJoin('chat_message_receipts as cmr', function ($join) use ($userId) {
                $join->on('cmr.message_id', '=', 'chat_messages.id')
                    ->where('cmr.user_id', '=', $userId);
            })
            ->where('chat_messages.room_type', $roomType)
            ->where('chat_messages.room_id', $roomId)
            ->where('chat_messages.user_id', '!=', $userId)
            ->where(function ($query) {
                $query->whereNull('cmr.id')->orWhereNull('cmr.read_at');
            })
            ->orderByDesc('chat_messages.id')
            ->limit($limit)
            ->pluck('chat_messages.id')
            ->all();

        if (empty($messageIds)) {
            return false;
        }

        $now = CarbonImmutable::now();
        $rows = array_map(fn (int $messageId) => [
            'message_id' => $messageId,
            'user_id' => $userId,
            'delivered_at' => $now,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $messageIds);

        DB::table('chat_message_receipts')->upsert(
            $rows,
            ['message_id', 'user_id'],
            ['delivered_at', 'read_at', 'updated_at']
        );

        $this->forgetRoomCache($roomType, $roomId);

        return true;
    }

    public function forgetRoomCache(string $roomType, ?int $roomId): void
    {
        Redis::del($this->cacheKey($roomType, $roomId, 100));
    }

    private function cacheKey(string $roomType, ?int $roomId, int $limit): string
    {
        return sprintf('chat:room:%s:%s:messages:%d', $roomType, $roomId ?? 'global', $limit);
    }
}
