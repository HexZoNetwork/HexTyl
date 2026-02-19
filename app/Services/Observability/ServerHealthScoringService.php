<?php

namespace Pterodactyl\Services\Observability;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerHealthScore;

class ServerHealthScoringService
{
    public function recalculate(Server $server): ServerHealthScore
    {
        $crashPenalty = 0;
        $restartPenalty = 0;
        $snapshotPenalty = 0;
        $reason = 'Healthy baseline';

        if (in_array($server->status, [Server::STATUS_INSTALL_FAILED, Server::STATUS_REINSTALL_FAILED], true)) {
            $crashPenalty += 15;
            $reason = 'Install/reinstall failures detected';
        }

        if ($server->status === Server::STATUS_SUSPENDED) {
            $restartPenalty += 10;
            $reason = 'Repeated operational issues (suspended)';
        }

        if (!$server->installed_at || $server->installed_at->diffInDays(now()) < 2) {
            $snapshotPenalty += 5;
        }

        $index = max(0, min(100, 100 - $crashPenalty - $restartPenalty - $snapshotPenalty));

        return ServerHealthScore::query()->updateOrCreate(
            ['server_id' => $server->id],
            [
                'stability_index' => $index,
                'crash_penalty' => $crashPenalty,
                'restart_penalty' => $restartPenalty,
                'snapshot_penalty' => $snapshotPenalty,
                'last_reason' => $reason,
                'last_calculated_at' => now(),
            ]
        );
    }

    public function recalculateAll(): void
    {
        Server::query()->select(['id', 'status', 'installed_at'])->chunk(200, function ($servers): void {
            foreach ($servers as $server) {
                $this->recalculate($server);
            }
        });
    }
}
