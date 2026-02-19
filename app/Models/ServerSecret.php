<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerSecret extends Model
{
    protected $table = 'server_secrets';

    protected $fillable = [
        'server_id',
        'secret_key',
        'encrypted_value',
        'last_accessed_at',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'last_accessed_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
