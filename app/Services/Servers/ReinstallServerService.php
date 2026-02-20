<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Security\NodeSecureModeService;

class ReinstallServerService
{
    /**
     * ReinstallService constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private DaemonServerRepository $daemonServerRepository,
        private NodeSecureModeService $nodeSecureModeService,
    ) {
    }

    /**
     * Reinstall a server on the remote daemon.
     *
     * @throws \Throwable
     */
    public function handle(Server $server): Server
    {
        $this->nodeSecureModeService->enforceDeployGate($server->loadMissing('node'));

        return $this->connection->transaction(function () use ($server) {
            $server->fill(['status' => Server::STATUS_INSTALLING])->save();

            $this->daemonServerRepository->setServer($server)->reinstall();

            return $server->refresh();
        });
    }
}
