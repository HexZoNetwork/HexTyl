<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerReputation;

class ServerReputationService
{
    /**
     * Recalculate and persist trust scores for a server.
     */
    public function recalculate(Server $server): ServerReputation
    {
        $stability = $this->calculateStability($server);
        $uptime = $this->calculateUptime($server);
        $abuse = $this->calculateAbuse($server);

        $trust = (int) round(($stability * 0.4) + ($uptime * 0.35) + ($abuse * 0.25));
        $trust = $this->bound($trust);

        return ServerReputation::query()->updateOrCreate(
            ['server_id' => $server->id],
            [
                'stability_score' => $stability,
                'uptime_score' => $uptime,
                'abuse_score' => $abuse,
                'trust_score' => $trust,
                'last_calculated_at' => now(),
            ]
        );
    }

    private function calculateStability(Server $server): int
    {
        if ($server->status === Server::STATUS_SUSPENDED) {
            return 15;
        }

        if (in_array($server->status, [Server::STATUS_INSTALL_FAILED, Server::STATUS_REINSTALL_FAILED], true)) {
            return 30;
        }

        if ($server->status === Server::STATUS_INSTALLING) {
            return 55;
        }

        return 88;
    }

    private function calculateUptime(Server $server): int
    {
        if (!$server->installed_at) {
            return 35;
        }

        $days = max(0, $server->installed_at->diffInDays(now()));
        $score = 45 + min(50, (int) floor($days / 3));

        return $this->bound($score);
    }

    private function calculateAbuse(Server $server): int
    {
        if ($server->status === Server::STATUS_SUSPENDED) {
            return 20;
        }

        return 85;
    }

    private function bound(int $score): int
    {
        return max(0, min(100, $score));
    }
}
