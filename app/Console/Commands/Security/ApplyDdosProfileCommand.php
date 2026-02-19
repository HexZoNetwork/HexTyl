<?php

namespace Pterodactyl\Console\Commands\Security;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplyDdosProfileCommand extends Command
{
    protected $signature = 'security:ddos-profile
                            {profile : normal|elevated|under_attack}
                            {--whitelist= : Comma-separated whitelist IP/CIDR, used for under_attack profile}';

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
                'ddos_whitelist_ips' => trim($whitelistOption) !== '' ? trim($whitelistOption) : '127.0.0.1,::1',
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
}
