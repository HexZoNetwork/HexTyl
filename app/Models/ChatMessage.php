<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    public const RESOURCE_NAME = 'chat_message';

    public const ROOM_SERVER = 'server';
    public const ROOM_GLOBAL = 'global';

    protected $table = 'chat_messages';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'room_id' => 'integer',
        'user_id' => 'integer',
        'reply_to_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ChatMessageReceipt::class, 'message_id');
    }
}
