<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBusEvent extends Model
{
    protected $table = 'event_bus_events';

    public $timestamps = false;

    protected $fillable = [
        'event_key',
        'source',
        'server_id',
        'actor_user_id',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'actor_user_id' => 'integer',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
