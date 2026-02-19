<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessageReceipt extends Model
{
    protected $table = 'chat_message_receipts';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'message_id' => 'integer',
        'user_id' => 'integer',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
