<?php

namespace Pterodactyl\Console\Commands\Security;

use Illuminate\Console\Command;
use Pterodactyl\Services\Security\ReputationNetworkService;

class RunReputationNetworkSyncCommand extends Command
{
    protected $signature = 'security:reputation-sync';

    protected $description = 'Sync shared reputation indicators with opt-in reputation network.';

    public function __construct(private ReputationNetworkService $reputationNetworkService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->reputationNetworkService->sync();
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
