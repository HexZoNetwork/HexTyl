<?php

namespace Pterodactyl\Services\Nodes;

use Pterodactyl\Models\Node;
use Illuminate\Support\Collection;

class ResourcePoolService
{
    /**
     * Find the best node for placement based on current load and specs.
     */
    public function findBestNode(int $requiredRam, int $requiredCpu): ?Node
    {
        // Get all public, active nodes
        $nodes = Node::where('public', true)->get();

        // Sort by "fitness" score
        // Score = (Free RAM * 0.6) + (Free CPU * 0.4) - (Disk IO Load * 0.5)
        
        $bestNode = $nodes->sortByDesc(function (Node $node) use ($requiredRam) {
            $freeRam = $node->memory * (1 - ($node->memory_overallocate / 100)) - $node->allocated_resources['memory'];
            
            if ($freeRam < $requiredRam) {
                return -1; // Not enough RAM
            }

            // In reality, we'd query real-time metrics here from Wings
            // For now, we use allocation data spread.
            
            return $freeRam;
        })->first();

        return $bestNode;
    }
}
