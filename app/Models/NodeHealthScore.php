<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeHealthScore extends Model
{
    protected $table = 'node_health_scores';

    protected $fillable = [
        'node_id',
        'health_score',
        'reliability_rating',
        'crash_frequency',
        'placement_score',
        'migration_recommendation',
        'last_calculated_at',
    ];

    protected $casts = [
        'node_id' => 'integer',
        'health_score' => 'integer',
        'reliability_rating' => 'integer',
        'crash_frequency' => 'integer',
        'placement_score' => 'integer',
        'last_calculated_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
