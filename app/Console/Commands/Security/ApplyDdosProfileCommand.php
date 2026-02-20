<?php

namespace Pterodactyl\Console\Commands\Security;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;

class ApplyDdosProfileCommand extends Command
{
    protected $signature = 'security:ddos-profile
                            {profile : normal|elevated|under_attack}
                            {--whitelist= : Comma-separated whitelist IP/CIDR, used for under_attack profile. Empty keeps existing list}';

    protected $description = 'Apply anti-DDoS profile values to system_settings.';

    public function handle(): int
    {
        $profile = (string) $this->argument('profile');
        $whitelistOption = (string) ($this->option('whitelist') ?? '');

        try {
            $settings = $this->settingsForProfile($profile, $whitelistOption);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $now = now();
        foreach ($settings as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) $value, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach (array_keys($settings) as $key) {
            Cache::forget("system:{$key}");
        }

        $this->info(sprintf('Applied DDOS profile "%s".', $profile));

        return 0;
    }

    private function settingsForProfile(string $profile, string $whitelistOption): array
    {
        return match ($profile) {
            'normal' => [
                'ddos_lockdown_mode' => 'false',
                'ddos_whitelist_ips' => '127.0.0.1,::1',
                'ddos_rate_web_per_minute' => 180,
                'ddos_rate_api_per_minute' => 120,
                'ddos_rate_login_per_minute' => 20,
                'ddos_rate_write_per_minute' => 40,
                'ddos_burst_threshold_10s' => 150,
                'ddos_temp_block_minutes' => 10,
            ],
            'elevated' => [
                'ddos_lockdown_mode' => 'false',
                'ddos_whitelist_ips' => '127.0.0.1,::1',
                'ddos_rate_web_per_minute' => 120,
                'ddos_rate_api_per_minute' => 80,
                'ddos_rate_login_per_minute' => 10,
                'ddos_rate_write_per_minute' => 25,
                'ddos_burst_threshold_10s' => 100,
                'ddos_temp_block_minutes' => 30,
            ],
            'under_attack' => [
                'ddos_lockdown_mode' => 'true',
                'ddos_whitelist_ips' => $this->validatedWhitelist($whitelistOption),
                'ddos_rate_web_per_minute' => 60,
                'ddos_rate_api_per_minute' => 40,
                'ddos_rate_login_per_minute' => 5,
                'ddos_rate_write_per_minute' => 10,
                'ddos_burst_threshold_10s' => 60,
                'ddos_temp_block_minutes' => 60,
            ],
            default => throw new InvalidArgumentException('Profile must be one of: normal, elevated, under_attack.'),
        };
    }

    private function validatedWhitelist(string $whitelistOption): string
    {
        $value = trim($whitelistOption);
        if ($value === '') {
            $value = (string) (DB::table('system_settings')->where('key', 'ddos_whitelist_ips')->value('value') ?? '');
        }

        $entries = array_values(array_filter(array_map('trim', explode(',', $value))));
        $entries = array_values(array_unique(array_merge(['127.0.0.1', '::1'], $entries)));

        $operatorIp = $this->detectOperatorIp();
        if ($operatorIp !== null && !in_array($operatorIp, $entries, true)) {
            $entries[] = $operatorIp;
            $this->line(sprintf('Auto-whitelisted operator IP from SSH session: %s', $operatorIp));
        }

        foreach ($entries as $entry) {
            if ($entry === '*') {
                continue;
            }

            if (str_contains($entry, '/')) {
                if (!IpUtils::checkIp('127.0.0.1', $entry) && !IpUtils::checkIp('::1', $entry)) {
                    throw new InvalidArgumentException(sprintf('Invalid CIDR entry in whitelist: %s', $entry));
                }

                continue;
            }

            if (filter_var($entry, FILTER_VALIDATE_IP) === false) {
                throw new InvalidArgumentException(sprintf('Invalid IP entry in whitelist: %s', $entry));
            }
        }

        $finalValue = implode(',', $entries);
        if (strlen($finalValue) > 3000) {
            throw new InvalidArgumentException('Whitelist value is too long (max 3000 chars).');
        }

        return $finalValue;
    }

    private function detectOperatorIp(): ?string
    {
        $candidates = [
            (string) env('SSH_CLIENT', ''),
            (string) env('SSH_CONNECTION', ''),
        ];

        foreach ($candidates as $candidate) {
            $firstToken = trim(strtok($candidate, ' '));
            if ($firstToken !== '' && filter_var($firstToken, FILTER_VALIDATE_IP) !== false) {
                return $firstToken;
            }
        }

        return null;
    }
}
