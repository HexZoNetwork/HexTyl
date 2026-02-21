<?php

namespace Pterodactyl\Console\Commands\Security;

use Illuminate\Console\Command;
use Pterodactyl\Services\Security\ResourceSafetyOrchestratorService;

class RunResourceSafetyCommand extends Command
{
    protected $signature = 'security:resource-safety
                            {--server-id= : Evaluate only one server id}
                            {--force : Run even when resource safety is disabled}';

    protected $description = 'Run resource spike safety orchestration and auto-enforce protection rules.';

    public function handle(ResourceSafetyOrchestratorService $service): int
    {
        $serverId = $this->option('server-id');
        $serverId = is_numeric($serverId) ? (int) $serverId : null;
        $force = (bool) $this->option('force');

        $summary = $service->runCycle($serverId, $force);

        $this->line(sprintf(
            'enabled=%s checked=%d violations=%d wings_incidents=%d enforced=%d stopped=%d suspended=%d deleted_servers=%d deleted_users=%d ip_bans=%d errors=%d skipped=%s',
            !empty($summary['enabled']) ? 'yes' : 'no',
            (int) ($summary['checked'] ?? 0),
            (int) ($summary['violations'] ?? 0),
            (int) ($summary['wings_incidents'] ?? 0),
            (int) ($summary['enforced'] ?? 0),
            (int) ($summary['stopped'] ?? 0),
            (int) ($summary['suspended'] ?? 0),
            (int) ($summary['deleted_servers'] ?? 0),
            (int) ($summary['deleted_users'] ?? 0),
            (int) ($summary['permanent_ip_bans'] ?? 0),
            (int) ($summary['errors'] ?? 0),
            !empty($summary['skipped']) ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }
}
