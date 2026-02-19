<?php

namespace Pterodactyl\Services\Environment;

use Illuminate\Support\Facades\File;
use Pterodactyl\Exceptions\DisplayException;

class EnvironmentService
{
    private string $envPath;

    public function __construct()
    {
        $this->envPath = base_path('.env');
    }

    /**
     * Get the content of .env file.
     */
    public function get(): string
    {
        if (!File::exists($this->envPath)) {
            return '';
        }
        return File::get($this->envPath);
    }

    /**
     * Update a specific key in the .env file.
     */
    public function updateKey(string $key, string $value): void
    {
        $content = File::get($this->envPath);
        $escaped = preg_quote('=' . env($key), '/');

        if (strpos($content, $key . '=') === false) {
             // Append if not exists
             $content .= "\n{$key}={$value}";
        } else {
             // Replace
             $content = preg_replace(
                 "/^{$key}=.*/m",
                 "{$key}={$value}",
                 $content
             );
        }

        File::put($this->envPath, $content);
    }

    /**
     * Encrypt sensitive values (Mock implementation for now).
     */
    public function encryptValue(string $value): string
    {
        // In real implementation, use Defuse or similar library
        return 'ENC(' . base64_encode($value) . ')';
    }
}
