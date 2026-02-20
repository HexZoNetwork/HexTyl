<?php

namespace Pterodactyl\Console\Commands\Security;

use Illuminate\Console\Command;
use Pterodactyl\Services\Security\TrustAutomationService;

class RunTrustAutomationCommand extends Command
{
    protected $signature = 'security:trust-automation
                            {--server= : Evaluate one server ID only}
                            {--force : Run even when trust automation is disabled}';

    protected $description = 'Run trust score automation rules and enforce quarantine/lockdown actions.';

    public function __construct(private TrustAutomationService $trustAutomationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $serverId = $this->option('server');
        $targetServerId = is_numeric($serverId) ? (int) $serverId : null;
        $summary = $this->trustAutomationService->runCycle($targetServerId, (bool) $this->option('force'));

        if (!empty($summary['skipped'])) {
            $this->line('Trust automation disabled. Nothing executed.');

            return 0;
        }

        $this->line(sprintf(
            'checked=%d recalculated=%d elevated=%d quarantined=%d lockdown=%d',
            (int) $summary['checked'],
            (int) $summary['recalculated'],
            (int) $summary['elevated_applied'],
            (int) $summary['quarantined'],
            (int) $summary['lockdown_triggered']
        ));

        return 0;
    }
}
