<?php

namespace Pterodactyl\Services\Observability;

use Illuminate\Database\Eloquent\Builder;
use Pterodactyl\Models\SecurityEvent;

class RootAuditTimelineService
{
    public function query(array $filters = []): Builder
    {
        $query = SecurityEvent::query()->with(['actor:id,username', 'server:id,name,uuid']);

        if (!empty($filters['user_id'])) {
            $query->where('actor_user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['server_id'])) {
            $query->where('server_id', (int) $filters['server_id']);
        }

        if (!empty($filters['risk_level'])) {
            $query->where('risk_level', (string) $filters['risk_level']);
        }

        if (!empty($filters['event_type'])) {
            $query->where('event_type', (string) $filters['event_type']);
        }

        return $query->orderByDesc('created_at');
    }
}
