<?php

namespace Pterodactyl\Models;

class ReputationIndicator extends Model
{
    protected $table = 'reputation_indicators';

    protected $fillable = [
        'indicator_type',
        'indicator_value',
        'source',
        'confidence',
        'risk_level',
        'meta',
        'last_seen_at',
        'expires_at',
    ];

    protected $casts = [
        'confidence' => 'integer',
        'meta' => 'array',
        'last_seen_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
