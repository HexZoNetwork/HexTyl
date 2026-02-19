<?php

namespace Pterodactyl\Services\Schedules;

use Pterodactyl\Models\Schedule;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Exceptions\DisplayException;

class CronManagerService
{
    /**
     * Create a new cron schedule for a server.
     */
    public function create(Server $server, array $data): Schedule
    {
        return DB::transaction(function () use ($server, $data) {
            $schedule = new Schedule();
            $schedule->server_id = $server->id;
            $schedule->name = $data['name'];
            $schedule->cron_minute = $data['minute'] ?? '*';
            $schedule->cron_hour = $data['hour'] ?? '*';
            $schedule->cron_day_of_month = $data['day_of_month'] ?? '*';
            $schedule->cron_month = $data['month'] ?? '*';
            $schedule->cron_day_of_week = $data['day_of_week'] ?? '*';
            $schedule->is_active = true;
            $schedule->is_processing = false;
            $schedule->save();

            return $schedule;
        });
    }

    /**
     * Toggle a schedule's active state.
     */
    public function toggle(Schedule $schedule): bool
    {
        $schedule->is_active = !$schedule->is_active;
        $schedule->save();

        return $schedule->is_active;
    }
    
    /**
     * Parse cron expression to human readable.
     * (Placeholder for logic to convert "* * * * *" to "Every minute")
     */
    public function toHumanReadable(Schedule $schedule): string
    {
        return sprintf(
            '%s %s %s %s %s',
            $schedule->cron_minute,
            $schedule->cron_hour,
            $schedule->cron_day_of_month,
            $schedule->cron_month,
            $schedule->cron_day_of_week
        );
    }
}
