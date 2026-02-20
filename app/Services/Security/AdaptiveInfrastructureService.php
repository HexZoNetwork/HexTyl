<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\AdaptiveBaseline;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\NodeHealthScore;
use Pterodactyl\Models\SecurityEvent;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerHealthScore;
use Pterodactyl\Models\ServerReputation;
use Pterodactyl\Services\Ecosystem\EventBusService;

class AdaptiveInfrastructureService
{
    public function __construct(
        private SecurityEventService $securityEventService,
        private EventBusService $eventBusService
    ) {
    }

    public function runCycle(): array
    {
        $alpha = $this->settingFloat('adaptive_alpha', 0.2, 0.05, 0.8);
        $zThreshold = $this->settingFloat('adaptive_z_threshold', 2.5, 1.2, 8.0);

        $anomalies = 0;
        $samples = 0;
        ServerReputation::query()->chunk(200, function ($rows) use ($alpha, $zThreshold, &$anomalies, &$samples): void {
            foreach ($rows as $row) {
                $samples++;
                $z = $this->updateBaseline((int) $row->server_id, 'trust_score', (float) $row->trust_score, $alpha);
                if ($z >= $zThreshold) {
                    $anomalies++;
                    $this->securityEventService->log('security:adaptive.trust_anomaly', [
                        'server_id' => (int) $row->server_id,
                        'risk_level' => $z >= ($zThreshold + 1.5) ? 'high' : 'medium',
                        'meta' => [
                            'metric' => 'trust_score',
                            'value' => (float) $row->trust_score,
                            'z_score' => round($z, 3),
                        ],
                    ]);
                    $this->eventBusService->emit('adaptive.trust.anomaly', [
                        'server_id' => (int) $row->server_id,
                        'z_score' => round($z, 3),
                        'value' => (float) $row->trust_score,
                    ], 'adaptive', (int) $row->server_id);
                }
            }
        });

        $tuned = $this->autoTuneDdosThreshold();

        return [
            'success' => true,
            'samples' => $samples,
            'anomalies' => $anomalies,
            'ddos_threshold' => $tuned,
        ];
    }

    public function overview(): array
    {
        $topAnomalies = AdaptiveBaseline::query()
            ->with('server:id,name,uuid')
            ->where('anomaly_score', '>=', $this->settingFloat('adaptive_z_threshold', 2.5, 1.2, 8.0))
            ->orderByDesc('anomaly_score')
            ->limit(25)
            ->get();

        $blastRadius = Node::query()
            ->withCount('servers')
            ->with('healthScore')
            ->get()
            ->map(function (Node $node) {
                $health = (int) optional($node->healthScore)->health_score;
                $serverCount = (int) $node->servers_count;
                $score = max(0, min(100, (int) round(($serverCount * 3) + (100 - $health))));

                return [
                    'node_id' => $node->id,
                    'node_name' => $node->name,
                    'server_count' => $serverCount,
                    'health_score' => $health,
                    'blast_radius_score' => $score,
                ];
            })
            ->sortByDesc('blast_radius_score')
            ->values();

        $topology = $this->topologyMap();

        return [
            'anomaly_baselines' => $topAnomalies,
            'blast_radius' => $blastRadius,
            'topology' => $topology,
            'server_health' => ServerHealthScore::query()->orderBy('stability_index')->limit(20)->get(),
            'node_health' => NodeHealthScore::query()->orderBy('health_score')->limit(20)->get(),
        ];
    }

    public function topologyMap(): array
    {
        $nodes = Node::query()->select(['id', 'name', 'fqdn'])->get();
        $servers = Server::query()->select(['id', 'name', 'uuid', 'node_id', 'status'])->get();

        $edges = $servers->map(fn (Server $server) => [
            'from_node_id' => $server->node_id,
            'to_server_id' => $server->id,
            'type' => 'node_server',
            'status' => $server->status,
        ])->values();

        return [
            'nodes' => $nodes,
            'servers' => $servers,
            'edges' => $edges,
        ];
    }

    private function updateBaseline(?int $serverId, string $metricKey, float $value, float $alpha): float
    {
        $baseline = AdaptiveBaseline::query()->firstOrCreate(
            ['server_id' => $serverId, 'metric_key' => $metricKey],
            [
                'ewma' => $value,
                'variance' => 1.0,
                'last_value' => $value,
                'anomaly_score' => 0.0,
                'sample_count' => 0,
                'last_seen_at' => now(),
            ]
        );

        $delta = $value - (float) $baseline->ewma;
        $ewma = (1 - $alpha) * (float) $baseline->ewma + ($alpha * $value);
        $variance = (1 - $alpha) * (float) $baseline->variance + ($alpha * ($delta * $delta));
        $z = abs($delta) / sqrt(max(0.0001, $variance));

        $baseline->forceFill([
            'ewma' => $ewma,
            'variance' => $variance,
            'last_value' => $value,
            'anomaly_score' => $z,
            'sample_count' => (int) $baseline->sample_count + 1,
            'last_seen_at' => now(),
        ])->save();

        return $z;
    }

    private function autoTuneDdosThreshold(): int
    {
        $current = (int) (DB::table('system_settings')->where('key', 'ddos_burst_threshold_10s')->value('value') ?? 150);
        $min = 40;
        $max = 800;

        $bursts = SecurityEvent::query()
            ->whereIn('event_type', ['security:ddos.temp_block', 'security:rate_limit.hit'])
            ->where('created_at', '>=', now()->subMinutes(30))
            ->count();

        $target = $current;
        if ($bursts >= 100) {
            $target = max($min, $current - 10);
        } elseif ($bursts <= 8) {
            $target = min($max, $current + 5);
        }

        if ($target !== $current) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'ddos_burst_threshold_10s'],
                ['value' => (string) $target, 'created_at' => now(), 'updated_at' => now()]
            );
            Cache::forget('system:ddos_burst_threshold_10s');

            $this->securityEventService->log('security:adaptive.ddos_threshold_tuned', [
                'risk_level' => 'medium',
                'meta' => [
                    'from' => $current,
                    'to' => $target,
                    'bursts_30m' => $bursts,
                ],
            ]);
            $this->eventBusService->emit('adaptive.ddos.tuned', [
                'from' => $current,
                'to' => $target,
                'bursts_30m' => $bursts,
            ], 'adaptive');
        }

        return $target;
    }

    private function settingFloat(string $key, float $default, float $min, float $max): float
    {
        $raw = (string) (DB::table('system_settings')->where('key', $key)->value('value') ?? (string) $default);
        $value = (float) $raw;

        return max($min, min($max, $value));
    }
}
