<?php

namespace Pterodactyl\Services\Nodes;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;

class FailoverService
{
    /**
     * Migrate servers from a dead node to a healthy one in the same location.
     */
    public function handleNodeFailure(Node $deadNode): void
    {
        Log::warning("Initiating failover for node {$deadNode->name} ({$deadNode->id})");

        $servers = $deadNode->servers;

        // Find a suitable target node in the same location
        $targetNode = Node::where('location_id', $deadNode->location_id)
            ->where('id', '!=', $deadNode->id)
            ->where('public', true) // assuming 'public' denotes availability
            ->first();

        if (!$targetNode) {
            Log::error("No target node found for failover in location {$deadNode->location_id}");
            return;
        }

        foreach ($servers as $server) {
            try {
                $this->migrateServer($server, $targetNode);
            } catch (\Exception $e) {
                Log::error("Failed to migrate server {$server->id}: " . $e->getMessage());
            }
        }
    }

    protected function migrateServer(Server $server, Node $target): void
    {
        // Update server to point to new node
        // In reality, this requires moving files (which might be impossible if the old node is dead)
        // BUT, if using shared storage (NFS/S3), we just update the node_id.
        
        // Assuming Shared Storage for "High Availability" setup:
        $server->node_id = $target->id;
        $server->save();
        
        // Trigger reinstall/rebuild on new node
        // $this->reinstallService->handle($server);
        
        Log::info("Migrated server {$server->id} to node {$target->id}");
    }
}
