<?php

namespace Pterodactyl\Services\Backups;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Backup;
use Pterodactyl\Services\Backups\BackupCreationService;
use Exception;

class SnapshotService
{
    public function __construct(private BackupCreationService $backupCreationService)
    {
    }

    /**
     * Create a snapshot before an update.
     */
    public function createSnapshot(Server $server, string $name = null): Backup
    {
        $name = $name ?? 'Snapshot - ' . now()->toDateTimeString();
        
        // Delegate to existing backup service
        // lock = true to prevent accidental deletion
        return $this->backupCreationService->handle($server, [
            'name' => $name,
            'is_locked' => true,
        ]);
    }

    /**
     * Restore a snapshot.
     */
    public function restoreSnapshot(Server $server, Backup $backup): void
    {
        if ($backup->server_id !== $server->id) {
            throw new Exception("Backup does not belong to this server.");
        }

        // Trigger restore logic via Wings
        // $this->daemonBackupRepository->restore($server, $backup);
    }
}
