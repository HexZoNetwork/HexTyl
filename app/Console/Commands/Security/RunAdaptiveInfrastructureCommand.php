<?php

namespace Pterodactyl\Console\Commands\Security;

use Illuminate\Console\Command;
use Pterodactyl\Services\Security\AdaptiveInfrastructureService;

class RunAdaptiveInfrastructureCommand extends Command
{
    protected $signature = 'security:adaptive-cycle';

    protected $description = 'Run adaptive baseline and self-optimizing security tuning cycle.';

    public function __construct(private AdaptiveInfrastructureService $adaptiveInfrastructureService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $summary = $this->adaptiveInfrastructureService->runCycle();
        $this->line(sprintf(
            'samples=%d anomalies=%d ddos_threshold=%d',
            (int) ($summary['samples'] ?? 0),
            (int) ($summary['anomalies'] ?? 0),
            (int) ($summary['ddos_threshold'] ?? 0)
        ));

        return 0;
    }
}
