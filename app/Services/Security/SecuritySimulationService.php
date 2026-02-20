<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Facades\Cache;
use Pterodactyl\Services\Ecosystem\EventBusService;

class SecuritySimulationService
{
    public function __construct(
        private SecurityEventService $securityEventService,
        private TrustAutomationService $trustAutomationService,
        private EventBusService $eventBusService
    ) {
    }

    public function run(string $type, int $intensity = 1): array
    {
        $intensity = max(1, min(1000, $intensity));

        $result = match ($type) {
            'bruteforce' => $this->simulateBruteforce($intensity),
            'api_abuse' => $this->simulateApiAbuse($intensity),
            'burst' => $this->simulateBurst($intensity),
            'priv_escalation' => $this->simulatePrivilegeEscalation($intensity),
            default => throw new \InvalidArgumentException('Unknown simulation type.'),
        };

        $this->eventBusService->emit('security.simulation.completed', [
            'type' => $type,
            'intensity' => $intensity,
            'result' => $result,
        ], 'simulation');

        return $result;
    }

    private function simulateBruteforce(int $intensity): array
    {
        for ($i = 0; $i < $intensity; $i++) {
            $this->securityEventService->log('security:simulation.bruteforce', [
                'ip' => "203.0.113." . (($i % 200) + 1),
                'risk_level' => $i > 20 ? 'high' : 'medium',
                'meta' => ['attempt' => $i + 1, 'target' => '/auth/login'],
            ]);
        }

        return ['type' => 'bruteforce', 'events_logged' => $intensity];
    }

    private function simulateApiAbuse(int $intensity): array
    {
        for ($i = 0; $i < $intensity; $i++) {
            $this->securityEventService->log('security:simulation.api_abuse', [
                'ip' => "198.51.100." . (($i % 150) + 1),
                'risk_level' => $i > 30 ? 'high' : 'medium',
                'meta' => ['path' => '/api/application/servers', 'method' => 'POST'],
            ]);
        }

        return ['type' => 'api_abuse', 'events_logged' => $intensity];
    }

    private function simulateBurst(int $intensity): array
    {
        $window = (int) floor(time() / 10);
        Cache::put("ddos:auto_under_attack:signal:{$window}", $intensity, 45);

        $this->securityEventService->log('security:simulation.burst', [
            'risk_level' => 'high',
            'meta' => ['signal_10s' => $intensity],
        ]);

        return ['type' => 'burst', 'signal_10s' => $intensity];
    }

    private function simulatePrivilegeEscalation(int $intensity): array
    {
        for ($i = 0; $i < $intensity; $i++) {
            $this->securityEventService->log('security:simulation.priv_escalation', [
                'ip' => "192.0.2." . (($i % 120) + 1),
                'risk_level' => 'critical',
                'meta' => ['vector' => 'forged_scope', 'attempt' => $i + 1],
            ]);
        }

        // Run trust automation once to validate downstream trigger paths.
        $automation = $this->trustAutomationService->runCycle(null, true);

        return [
            'type' => 'priv_escalation',
            'events_logged' => $intensity,
            'trust_automation' => $automation,
        ];
    }
}
