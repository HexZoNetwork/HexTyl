<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdaptiveBaseline extends Model
{
    protected $table = 'adaptive_baselines';

    protected $fillable = [
        'server_id',
        'metric_key',
        'ewma',
        'variance',
        'last_value',
        'anomaly_score',
        'sample_count',
        'last_seen_at',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'ewma' => 'float',
        'variance' => 'float',
        'last_value' => 'float',
        'anomaly_score' => 'float',
        'sample_count' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
