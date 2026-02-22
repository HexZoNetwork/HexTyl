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

        if (!empty($filters['ip'])) {
            $query->where('ip', 'like', '%' . trim((string) $filters['ip']) . '%');
        }

        if (!empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('event_type', 'like', '%' . $term . '%')
                    ->orWhere('ip', 'like', '%' . $term . '%')
                    ->orWhere('meta', 'like', '%' . $term . '%');
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['date_to']);
        }

        return $query->orderByDesc('created_at');
    }

    public function summary(array $filters = []): array
    {
        $base = $this->query($filters)->toBase();

        return [
            'total' => (clone $base)->count(),
            'critical' => (clone $base)->where('risk_level', 'critical')->count(),
            'high' => (clone $base)->where('risk_level', 'high')->count(),
            'last_24h' => (clone $base)->where('created_at', '>=', now()->subDay())->count(),
        ];
    }
}
