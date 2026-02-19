<?php

namespace Pterodactyl\Services\Observability;

use Pterodactyl\Models\Server;

class CrashAnalyzerService
{
    /**
     * Analyze a crash log and return recommendations.
     */
    public function analyze(Server $server, string $logContent): array
    {
        $recommendation = 'Unknown cause. Check logs.';
        $reason = 'Generic Exit Code';

        if (str_contains($logContent, 'OutOfMemoryError') || str_contains($logContent, 'heap out of memory')) {
            $reason = 'OOM (Out Of Memory)';
            $recommendation = 'Upgrade RAM or optimize Java/Node startup flags.';
        }

        if (str_contains($logContent, 'Address already in use')) {
            $reason = 'Port Conflict';
            $recommendation = 'Check startup parameters or if another process is using the port.';
        }

        return [
            'reason' => $reason,
            'recommendation' => $recommendation,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
