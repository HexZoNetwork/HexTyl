<?php

namespace Pterodactyl\Services\Nodes;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Pterodactyl\Models\Node;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Contracts\Repository\NodeRepositoryInterface;

class NodeCreationService
{
    /**
     * NodeCreationService constructor.
     */
    public function __construct(protected NodeRepositoryInterface $repository)
    {
    }

    /**
     * Create a new node on the panel.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function handle(array $data): Node
    {
        $data = $this->sanitizeOverallocation($data);
        $data['uuid'] = Uuid::uuid4()->toString();
        $data['daemon_token'] = app(Encrypter::class)->encrypt(Str::random(Node::DAEMON_TOKEN_LENGTH));
        $data['daemon_token_id'] = Str::random(Node::DAEMON_TOKEN_ID_LENGTH);

        return $this->repository->create($data, true, true);
    }

    private function sanitizeOverallocation(array $data): array
    {
        foreach (['memory_overallocate', 'disk_overallocate'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $data[$key] = max(-1, min(1000, (int) $data[$key]));
        }

        return $data;
    }
}
