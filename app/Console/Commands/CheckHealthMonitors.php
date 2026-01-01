<?php

namespace App\Console\Commands;

use App\Models\HealthMonitor;
use App\Notifications\MonitorDown;
use App\Notifications\MonitorRecovered;
use App\Services\UptimeMonitor;
use Illuminate\Console\Command;

class CheckHealthMonitors extends Command
{
    protected $signature = 'monitors:check {--monitor= : Check a specific monitor ID}';

    protected $description = 'Run health checks for all active monitors';

    public function handle(UptimeMonitor $uptimeMonitor): int
    {
        $monitorId = $this->option('monitor');

        $query = HealthMonitor::where('is_active', true)
            ->where('status', '!=', HealthMonitor::STATUS_PAUSED);

        if ($monitorId) {
            $query->where('id', $monitorId);
        }

        $monitors = $query->get()->filter(fn ($m) => $m->needsCheck());

        if ($monitors->isEmpty()) {
            $this->info('No monitors need checking at this time.');
            return self::SUCCESS;
        }

        $this->info("Checking {$monitors->count()} monitors...");

        $results = ['up' => 0, 'down' => 0];

        foreach ($monitors as $monitor) {
            $wasDown = $monitor->isDown();
            $result = $uptimeMonitor->check($monitor);

            // Log the check result
            $monitor->logCheck(
                $result->success,
                $result->responseTime ?? null,
                $result->statusCode ?? null,
                $result->error ?? null
            );

            if ($result->success) {
                $monitor->markUp();
                $monitor->update(['last_response_time' => $result->responseTime]);
                $results['up']++;

                // Send recovery notification if was down
                if ($wasDown && $monitor->consecutive_successes >= $monitor->recovery_threshold) {
                    $this->sendRecoveryNotification($monitor);
                }
            } else {
                $monitor->markDown($result->error);
                $results['down']++;

                // Send down notification if just went down
                if (!$wasDown && $monitor->consecutive_failures >= $monitor->failure_threshold) {
                    $this->sendDownNotification($monitor, $result->error);
                }
            }

            // Update uptime stats periodically (every 10th check to reduce load)
            if ($monitor->logs()->count() % 10 === 0) {
                $monitor->updateUptimeStats();
            }

            $status = $result->success ? '<fg=green>UP</>' : '<fg=red>DOWN</>';
            $responseInfo = $result->success && $result->responseTime ? " ({$result->responseTime}ms)" : '';
            $this->line("  [{$status}] {$monitor->name} ({$monitor->check_target}){$responseInfo}");
        }

        $this->newLine();
        $this->info("Results: {$results['up']} up, {$results['down']} down");

        return self::SUCCESS;
    }

    protected function sendDownNotification(HealthMonitor $monitor, ?string $error): void
    {
        $owner = $monitor->team->owner;
        if ($owner) {
            $owner->notify(new MonitorDown($monitor, $error));
        }
    }

    protected function sendRecoveryNotification(HealthMonitor $monitor): void
    {
        $owner = $monitor->team->owner;
        if ($owner) {
            $owner->notify(new MonitorRecovered($monitor));
        }
    }
}
