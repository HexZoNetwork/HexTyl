<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\RiskSnapshot;

class ProgressiveSecurityModeService
{
    public const MODE_NORMAL = 'normal';
    public const MODE_ELEVATED = 'elevated';
    public const MODE_LOCKDOWN = 'lockdown';

    public function evaluateSystemMode(): string
    {
        $criticalIps = RiskSnapshot::query()->where('risk_score', '>=', 80)->count();
        $highIps = RiskSnapshot::query()->where('risk_score', '>=', 50)->count();

        $mode = self::MODE_NORMAL;
        if ($criticalIps >= 3) {
            $mode = self::MODE_LOCKDOWN;
        } elseif ($highIps >= 5) {
            $mode = self::MODE_ELEVATED;
        }

        $this->applyMode($mode);

        return $mode;
    }

    public function applyMode(string $mode): void
    {
        $mode = in_array($mode, [self::MODE_NORMAL, self::MODE_ELEVATED, self::MODE_LOCKDOWN], true)
            ? $mode
            : self::MODE_NORMAL;

        $this->setSetting('progressive_security_mode', $mode);

        if ($mode === self::MODE_LOCKDOWN) {
            $this->setSetting('silent_defense_mode', 'true');
            $this->setSetting('kill_switch_mode', 'true');
        }

        if ($mode === self::MODE_ELEVATED) {
            $this->setSetting('silent_defense_mode', 'true');
        }

        app(SecurityEventService::class)->log('security:mode.changed', [
            'risk_level' => $mode === self::MODE_LOCKDOWN ? 'critical' : ($mode === self::MODE_ELEVATED ? 'high' : 'info'),
            'meta' => ['mode' => $mode],
        ]);
    }

    public function currentMode(): string
    {
        return (string) (DB::table('system_settings')->where('key', 'progressive_security_mode')->value('value') ?: self::MODE_NORMAL);
    }

    private function setSetting(string $key, string $value): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
