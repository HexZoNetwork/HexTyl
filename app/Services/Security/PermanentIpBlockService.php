<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class PermanentIpBlockService
{
    public function __construct(private SecurityEventService $securityEventService)
    {
    }

    public function blockForever(string $ip, array $meta = []): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        Cache::forever("ddos:ban:{$ip}", true);
        Cache::forever("ddos:temp_block:{$ip}", true);

        $firewallApplied = false;
        try {
            $check = Process::fromShellCommandline('sudo -n true');
            $check->setTimeout(3);
            $check->run();

            if ($check->isSuccessful()) {
                $cmd = ['sudo', '-n', 'nft', 'add', 'element', 'inet', 'hextyl_ddos', 'blocklist', "{ {$ip} }"];
                $process = new Process($cmd);
                $process->setTimeout(5);
                $process->run();
                $firewallApplied = $process->isSuccessful();
            }
        } catch (\Throwable) {
            // Keep cache-level ban even if firewall command fails.
        }

        $this->securityEventService->log('security:ip.permanent_block', [
            'ip' => $ip,
            'risk_level' => 'critical',
            'meta' => array_merge($meta, [
                'firewall_applied' => $firewallApplied,
            ]),
        ]);

        return true;
    }
}

