<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\SecurityEvent;
use Pterodactyl\Services\Observability\RootAuditTimelineService;

class SecurityTimelineController extends Controller
{
    public function index(Request $request, RootAuditTimelineService $timelineService): View
    {
        $filters = $request->only(['user_id', 'server_id', 'risk_level', 'event_type']);
        $windowMinutes = max(5, min(10080, (int) $request->query('window_minutes', 1440)));

        $events = $timelineService->query($filters)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->paginate(50)
            ->appends($request->query());

        $baseQuery = SecurityEvent::query()->where('created_at', '>=', now()->subMinutes($windowMinutes));
        if (!empty($filters['user_id'])) {
            $baseQuery->where('actor_user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['server_id'])) {
            $baseQuery->where('server_id', (int) $filters['server_id']);
        }
        if (!empty($filters['risk_level'])) {
            $baseQuery->where('risk_level', (string) $filters['risk_level']);
        }
        if (!empty($filters['event_type'])) {
            $baseQuery->where('event_type', (string) $filters['event_type']);
        }

        $severity = (clone $baseQuery)
            ->selectRaw('risk_level, COUNT(*) as total')
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level');

        $perServer = (clone $baseQuery)
            ->selectRaw('server_id, COUNT(*) as total')
            ->whereNotNull('server_id')
            ->groupBy('server_id')
            ->orderByDesc('total')
            ->with('server:id,name,uuid')
            ->limit(20)
            ->get();

        $fingerprints = (clone $baseQuery)->get(['event_type', 'ip', 'meta'])
            ->map(function (SecurityEvent $event) {
                $meta = is_array($event->meta) ? $event->meta : [];
                $reason = trim((string) ($meta['reason'] ?? $meta['path'] ?? ''));
                $seed = sprintf('%s|%s|%s', $event->event_type, (string) ($event->ip ?? '-'), $reason);

                return [
                    'fingerprint' => substr(hash('sha1', $seed), 0, 16),
                    'event_type' => $event->event_type,
                    'ip' => $event->ip,
                    'reason' => $reason !== '' ? $reason : null,
                ];
            })
            ->groupBy('fingerprint')
            ->map(function ($group, $fingerprint) {
                $first = $group->first();

                return [
                    'fingerprint' => $fingerprint,
                    'event_type' => $first['event_type'],
                    'ip' => $first['ip'],
                    'reason' => $first['reason'],
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(20)
            ->values();

        return view('admin.security.timeline', compact('events', 'filters', 'severity', 'perServer', 'fingerprints', 'windowMinutes'));
    }
}
