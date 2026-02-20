<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookSubscription extends Model
{
    protected $table = 'webhook_subscriptions';

    protected $fillable = [
        'name',
        'url',
        'event_pattern',
        'secret',
        'enabled',
        'created_by',
        'delivery_success_count',
        'delivery_failed_count',
        'last_delivery_at',
        'last_delivery_status',
        'last_error',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'created_by' => 'integer',
        'delivery_success_count' => 'integer',
        'delivery_failed_count' => 'integer',
        'last_delivery_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
