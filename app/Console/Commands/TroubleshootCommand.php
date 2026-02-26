<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TroubleshootCommand extends Command
{
    protected $signature = 'p:troubleshoot {--json : Output JSON instead of a table}';

    protected $description = 'Run quick health checks for common panel issues.';

    public function handle(): int
    {
        $checks = [];

        $appKeySet = !empty((string) config('app.key'));
        $checks[] = $this->check('APP_KEY', $appKeySet, 'APP_KEY is set', 'Missing APP_KEY. Run: php artisan key:generate', 'fail');

        $appUrl = (string) config('app.url');
        $validUrl = str_starts_with($appUrl, 'http://') || str_starts_with($appUrl, 'https://');
        $checks[] = $this->check('APP_URL', $validUrl, "OK (${appUrl})", "Invalid APP_URL (${appUrl}). Must start with http:// or https://", 'fail');

        $checks[] = $this->check('APP_DEBUG', !config('app.debug'), 'APP_DEBUG is false', 'APP_DEBUG is true (not recommended for production)', 'warn');

        $envPath = base_path('.env');
        $checks[] = $this->check('.env file', is_file($envPath), '.env file exists', '.env file is missing', 'warn');

        $checks[] = $this->check('storage writable', is_writable(storage_path()), 'storage/ is writable', 'storage/ is not writable', 'fail');
        $checks[] = $this->check('bootstrap/cache writable', is_writable(base_path('bootstrap/cache')), 'bootstrap/cache is writable', 'bootstrap/cache is not writable', 'fail');

        $dbOk = true;
        $dbMessage = 'Database connection OK';
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $dbOk = false;
            $dbMessage = 'Database connection failed: ' . $e->getMessage();
        }
        $checks[] = $this->check('database', $dbOk, $dbMessage, $dbMessage, 'fail');

        $cacheDriver = (string) config('cache.default');
        $sessionDriver = (string) config('session.driver');
        $queueDriver = (string) config('queue.default');
        $usesRedis = in_array('redis', [$cacheDriver, $sessionDriver, $queueDriver], true);
        if ($usesRedis) {
            $redisOk = true;
            $redisMessage = 'Redis connection OK';
            try {
                $redis = app('redis')->connection();
                $pong = $redis->ping();
                if (!is_string($pong)) {
                    $redisOk = false;
                    $redisMessage = 'Redis ping failed';
                }
            } catch (Throwable $e) {
                $redisOk = false;
                $redisMessage = 'Redis connection failed: ' . $e->getMessage();
            }
            $checks[] = $this->check('redis', $redisOk, $redisMessage, $redisMessage, 'fail');
        } else {
            $checks[] = $this->check('redis', true, 'Redis not required by current drivers', 'Redis not required by current drivers', 'ok');
        }

        if ($queueDriver === 'database') {
            $jobsTable = Schema::hasTable('jobs');
            $checks[] = $this->check('queue jobs table', $jobsTable, 'jobs table exists', 'jobs table missing (run migrations)', 'fail');
        } elseif ($queueDriver === 'sync') {
            $checks[] = $this->check('queue driver', false, 'Queue driver OK', 'Queue driver is sync (no background jobs)', 'warn');
        } else {
            $checks[] = $this->check('queue driver', true, "Queue driver: ${queueDriver}", "Queue driver: ${queueDriver}", 'ok');
        }

        $mailDriver = (string) config('mail.default');
        $mailHost = (string) config('mail.mailers.smtp.host');
        $mailOk = true;
        $mailMessage = "Mail driver: ${mailDriver}";
        if ($mailDriver === 'smtp' && ($mailHost === '' || $mailHost === 'smtp.example.com')) {
            $mailOk = false;
            $mailMessage = 'Mail driver SMTP but host is missing or placeholder';
        }
        $checks[] = $this->check('mail', $mailOk, $mailMessage, $mailMessage, 'warn');

        if ($this->option('json')) {
            $this->line(json_encode($checks, JSON_PRETTY_PRINT));
            return 0;
        }

        $rows = [];
        foreach ($checks as $check) {
            $rows[] = [
                $check['name'],
                $this->formatStatus($check['status']),
                $check['details'],
            ];
        }

        $this->output->title('Panel Troubleshoot');
        $this->table(['Check', 'Status', 'Details'], $rows);
        $this->line('');
        $this->line('Suggested commands:');
        $this->line('  php artisan p:environment:setup');
        $this->line('  php artisan p:environment:mail');
        $this->line('  php artisan p:environment:database');
        $this->line('  php artisan queue:restart');

        return 0;
    }

    private function check(string $name, bool $ok, string $okMessage, string $failMessage, string $failStatus): array
    {
        return [
            'name' => $name,
            'status' => $ok ? 'ok' : $failStatus,
            'details' => $ok ? $okMessage : $failMessage,
        ];
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'ok' => '<fg=green>OK</>',
            'warn' => '<fg=yellow>WARN</>',
            default => '<fg=red>FAIL</>',
        };
    }
}
