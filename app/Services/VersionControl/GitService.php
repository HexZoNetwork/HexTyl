<?php

namespace Pterodactyl\Services\VersionControl;

use Illuminate\Support\Facades\Process;
use Pterodactyl\Exceptions\DisplayException;

class GitService
{
    /**
     * Pull the latest changes from the configured repository.
     */
    public function pull(string $path): string
    {
        if (!is_dir($path . '/.git')) {
            throw new DisplayException('The target directory is not a valid Git repository.');
        }

        $result = Process::path($path)->run('git pull origin main');

        if ($result->failed()) {
            throw new DisplayException('Git pull failed: ' . $result->errorOutput());
        }

        return $result->output();
    }

    /**
     * Get the current commit hash.
     */
    public function currentCommit(string $path): string
    {
        $result = Process::path($path)->run('git rev-parse HEAD');
        return trim($result->output());
    }
}
