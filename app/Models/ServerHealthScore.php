<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerHealthScore extends Model
{
    protected $table = 'server_health_scores';

    protected $fillable = [
        'server_id',
        'stability_index',
        'crash_penalty',
        'restart_penalty',
        'snapshot_penalty',
        'last_reason',
        'last_calculated_at',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'stability_index' => 'integer',
        'crash_penalty' => 'integer',
        'restart_penalty' => 'integer',
        'snapshot_penalty' => 'integer',
        'last_calculated_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
