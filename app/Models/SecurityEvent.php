<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    public const RISK_INFO = 'info';
    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';
    public const RISK_CRITICAL = 'critical';

    protected $table = 'security_events';

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'server_id',
        'ip',
        'event_type',
        'risk_level',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'actor_user_id' => 'integer',
        'server_id' => 'integer',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
