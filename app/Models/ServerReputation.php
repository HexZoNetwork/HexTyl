<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerReputation extends Model
{
    protected $table = 'server_reputations';

    protected $fillable = [
        'server_id',
        'stability_score',
        'uptime_score',
        'abuse_score',
        'trust_score',
        'last_calculated_at',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'stability_score' => 'integer',
        'uptime_score' => 'integer',
        'abuse_score' => 'integer',
        'trust_score' => 'integer',
        'last_calculated_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
