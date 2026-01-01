<?php

namespace App\Console\Commands;

use App\Models\HealthCheck;
use Illuminate\Console\Command;

class RunHealthChecks extends Command
{
    protected $signature = 'health-checks:run {--check= : Specific check ID to run}';

    protected $description = 'Run all enabled health checks';

    public function handle(): int
    {
        $checkId = $this->option('check');

        if ($checkId) {
            $check = HealthCheck::find($checkId);
            if (!$check) {
                $this->error("Health check {$checkId} not found");
                return 1;
            }
            $this->runCheck($check);
            return 0;
        }

        // Get all enabled checks that are due
        $checks = HealthCheck::where('is_enabled', true)
            ->where(function ($query) {
                $query->whereNull('last_checked_at')
                    ->orWhereRaw('last_checked_at <= NOW() - INTERVAL interval_minutes MINUTE');
            })
            ->get();

        $this->info("Running {$checks->count()} health checks...");

        foreach ($checks as $check) {
            $this->runCheck($check);
        }

        $this->info('Health checks completed');

        return 0;
    }

    protected function runCheck(HealthCheck $check): void
    {
        $this->line("Checking: {$check->name} ({$check->url})");

        try {
            $log = $check->performCheck();

            if ($log->isUp()) {
                $this->info("  ✓ UP - {$log->response_time_ms}ms");
            } else {
                $this->error("  ✗ DOWN - {$log->error}");
            }
        } catch (\Exception $e) {
            $this->error("  ✗ Error: {$e->getMessage()}");
        }
    }
}
