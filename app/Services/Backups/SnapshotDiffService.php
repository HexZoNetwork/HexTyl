<?php

namespace Pterodactyl\Services\Backups;

use Pterodactyl\Models\Backup;
use Illuminate\Support\Facades\Storage;

class SnapshotDiffService
{
    /**
     * Compare two backups/snapshots and return changed files.
     * This is an expensive operation and typically done by the Daemon.
     * Here we stub the logic.
     */
    public function diff(Backup $backupA, Backup $backupB): array
    {
        // 1. Mount/Extract both archives temporarily
        // 2. Run 'diff -qr' or similar
        // 3. Parse output
        
        // Mock result
        return [
            'added' => ['server.properties', 'logs/latest.log'],
            'modified' => ['config/settings.json'],
            'deleted' => ['temp_data.dat'],
        ];
    }
}
