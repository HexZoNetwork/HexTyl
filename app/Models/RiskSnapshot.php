<?php

namespace Pterodactyl\Models;

class RiskSnapshot extends Model
{
    protected $table = 'risk_snapshots';

    protected $fillable = [
        'identifier',
        'risk_score',
        'risk_mode',
        'geo_country',
        'last_seen_at',
    ];

    protected $casts = [
        'risk_score' => 'integer',
        'last_seen_at' => 'datetime',
    ];
}
