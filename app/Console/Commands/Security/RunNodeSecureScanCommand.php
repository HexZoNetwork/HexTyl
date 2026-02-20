<?php

namespace Pterodactyl\Console\Commands\Security;

use Illuminate\Console\Command;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Security\NodeSecureModeService;

class RunNodeSecureScanCommand extends Command
{
    protected $signature = 'security:node-secure-scan
                            {--server= : Target server ID}
                            {--path= : Custom absolute path to scan}
                            {--skip-npm : Skip npm audit stage}';

    protected $description = 'Run Node.js Secure Mode scan (safe deploy pattern scan + npm audit + security score).';

    public function __construct(private NodeSecureModeService $nodeSecureModeService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $server = null;
        $serverId = $this->option('server');
        if (is_numeric($serverId)) {
            $server = Server::query()->with('node')->find((int) $serverId);
            if (!$server) {
                $this->error('Server not found.');

                return 1;
            }
        }

        $path = is_string($this->option('path')) ? trim((string) $this->option('path')) : null;

        $safeDeploy = $this->nodeSecureModeService->runSafeDeployScan($server, $path);
        $this->line(sprintf(
            'safe_deploy_scan: files=%d warnings=%d',
            (int) ($safeDeploy['scanned_files'] ?? 0),
            (int) ($safeDeploy['warnings_count'] ?? 0)
        ));

        if (!$this->option('skip-npm')) {
            $audit = $this->nodeSecureModeService->runNpmAudit($server, $path);
            $severity = $audit['severity'] ?? [];
            $this->line(sprintf(
                'npm_audit: critical=%d high=%d moderate=%d low=%d block_deploy=%s',
                (int) ($severity['critical'] ?? 0),
                (int) ($severity['high'] ?? 0),
                (int) ($severity['moderate'] ?? 0),
                (int) ($severity['low'] ?? 0),
                !empty($audit['block_deploy']) ? 'yes' : 'no'
            ));
        }

        if ($server) {
            $score = $this->nodeSecureModeService->securityScore($server->id);
            $this->line(sprintf(
                'security_score: total=%d grade=%s dependency=%d secret=%d runtime=%d network=%d',
                (int) ($score['total'] ?? 0),
                (string) ($score['grade'] ?? 'N/A'),
                (int) ($score['scores']['dependency_security'] ?? 0),
                (int) ($score['scores']['secret_safety'] ?? 0),
                (int) ($score['scores']['runtime_stability'] ?? 0),
                (int) ($score['scores']['network_exposure'] ?? 0)
            ));
        }

        return 0;
    }
}
