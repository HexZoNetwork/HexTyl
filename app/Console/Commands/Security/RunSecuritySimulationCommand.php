<?php

namespace Pterodactyl\Console\Commands\Security;

use Illuminate\Console\Command;
use Pterodactyl\Services\Security\SecuritySimulationService;

class RunSecuritySimulationCommand extends Command
{
    protected $signature = 'security:simulate
                            {type : bruteforce|api_abuse|burst|priv_escalation}
                            {--intensity=100 : Number of simulated events/signals}';

    protected $description = 'Run built-in security simulation scenarios for stress testing.';

    public function __construct(private SecuritySimulationService $securitySimulationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = (string) $this->argument('type');
        $intensity = (int) $this->option('intensity');

        try {
            $result = $this->securitySimulationService->run($type, $intensity);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
