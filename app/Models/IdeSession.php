<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeSession extends Model
{
    protected $table = 'ide_sessions';

    protected $fillable = [
        'server_id',
        'user_id',
        'token_hash',
        'launch_url',
        'request_ip',
        'terminal_allowed',
        'extensions_allowed',
        'expires_at',
        'consumed_at',
        'revoked_at',
        'meta',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'user_id' => 'integer',
        'terminal_allowed' => 'boolean',
        'extensions_allowed' => 'boolean',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'meta' => 'array',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
