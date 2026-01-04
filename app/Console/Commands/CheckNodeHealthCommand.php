<?php

namespace App\Console\Commands;

use App\Models\SupervisorProgram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckNodeHealthCommand extends Command
{
    protected $signature = 'node:health-check {--program= : Check a specific program ID}';

    protected $description = 'Check health of Node.js applications via their health endpoints';

    public function handle(): int
    {
        $query = SupervisorProgram::query()
            ->whereNotNull('web_app_id')
            ->where('status', SupervisorProgram::STATUS_ACTIVE);

        if ($programId = $this->option('program')) {
            $query->where('id', $programId);
        }

        $programs = $query->get();

        if ($programs->isEmpty()) {
            $this->info('No Node.js programs to check.');
            return self::SUCCESS;
        }

        $this->info("Checking health of {$programs->count()} Node.js program(s)...");

        $checked = 0;
        $healthy = 0;
        $unhealthy = 0;

        foreach ($programs as $program) {
            if (!$program->needsHealthCheck()) {
                continue;
            }

            $url = $program->health_check_url;
            if (!$url) {
                continue;
            }

            $checked++;
            $this->line("  Checking: {$program->name} ({$url})");

            try {
                $response = Http::timeout(10)
                    ->withOptions(['verify' => false])
                    ->get($url);

                if ($response->successful()) {
                    $program->markHealthy();
                    $healthy++;
                    $this->info("    ✓ Healthy (HTTP {$response->status()})");
                } else {
                    $program->markUnhealthy();
                    $unhealthy++;
                    $this->warn("    ✗ Unhealthy (HTTP {$response->status()})");
                }
            } catch (\Exception $e) {
                $program->markUnhealthy();
                $unhealthy++;
                $this->error("    ✗ Failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Health check complete: {$checked} checked, {$healthy} healthy, {$unhealthy} unhealthy");

        return self::SUCCESS;
    }
}
