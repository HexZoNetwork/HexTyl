<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecretVaultVersion extends Model
{
    protected $table = 'secret_vault_versions';

    protected $fillable = [
        'server_id',
        'secret_key',
        'version',
        'encrypted_value',
        'rotates_at',
        'expires_at',
        'created_by',
        'access_count',
        'last_accessed_at',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'version' => 'integer',
        'created_by' => 'integer',
        'access_count' => 'integer',
        'rotates_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
