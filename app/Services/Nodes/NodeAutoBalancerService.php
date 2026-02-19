<?php

namespace Pterodactyl\Services\Nodes;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\NodeHealthScore;

class NodeAutoBalancerService
{
    public function recalculate(Node $node): NodeHealthScore
    {
        $serversCount = (int) $node->servers()->count();
        $memTotal = max(1, (int) $node->memory);
        $diskTotal = max(1, (int) $node->disk);

        $memPressure = min(100, (int) round(($serversCount * 256 / $memTotal) * 100));
        $diskPressure = min(100, (int) round(($serversCount * 1024 / $diskTotal) * 100));

        $crashFrequency = min(100, (int) round(($memPressure * 0.6) + ($diskPressure * 0.4)));
        $reliability = max(0, 100 - $crashFrequency);
        $placementScore = max(0, 100 - (int) round(($memPressure * 0.7) + ($diskPressure * 0.3)));
        $healthScore = max(0, min(100, (int) round(($reliability * 0.6) + ($placementScore * 0.4))));

        $recommendation = null;
        if ($healthScore < 50) {
            $recommendation = 'Recommend offloading high-CPU servers to healthier nodes.';
        } elseif ($placementScore < 60) {
            $recommendation = 'Recommend placing new servers on lower pressure nodes.';
        }

        return NodeHealthScore::query()->updateOrCreate(
            ['node_id' => $node->id],
            [
                'health_score' => $healthScore,
                'reliability_rating' => $reliability,
                'crash_frequency' => $crashFrequency,
                'placement_score' => $placementScore,
                'migration_recommendation' => $recommendation,
                'last_calculated_at' => now(),
            ]
        );
    }

    public function recalculateAll(): void
    {
        Node::query()->select(['id', 'memory', 'disk'])->chunk(100, function ($nodes): void {
            foreach ($nodes as $node) {
                $this->recalculate($node);
            }
        });
    }
}
