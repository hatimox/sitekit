<?php

namespace App\Console\Commands;

use App\Models\ServiceStat;
use App\Models\Team;
use Illuminate\Console\Command;

class PruneServiceStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service-stats:prune {--days= : Override default retention days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old service statistics based on team retention settings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $overrideDays = $this->option('days');

        if ($overrideDays) {
            // Global prune with specified days
            $deleted = ServiceStat::pruneOlderThan((int) $overrideDays);
            $this->info("Pruned {$deleted} service stats older than {$overrideDays} days.");
            return self::SUCCESS;
        }

        // Prune per-team based on their retention settings
        $totalDeleted = 0;

        Team::whereHas('servers.services.stats')->each(function (Team $team) use (&$totalDeleted) {
            $retentionDays = $team->metrics_retention_days ?? 30;
            $cutoffDate = now()->subDays($retentionDays);

            // Get service IDs for this team's servers
            $serviceIds = $team->servers()
                ->with('services')
                ->get()
                ->flatMap(fn ($server) => $server->services->pluck('id'))
                ->toArray();

            if (empty($serviceIds)) {
                return;
            }

            $deleted = ServiceStat::whereIn('service_id', $serviceIds)
                ->where('recorded_at', '<', $cutoffDate)
                ->delete();

            $totalDeleted += $deleted;

            if ($deleted > 0) {
                $this->line("  Team '{$team->name}': pruned {$deleted} stats (retention: {$retentionDays} days)");
            }
        });

        $this->info("Total pruned: {$totalDeleted} service stats.");

        return self::SUCCESS;
    }
}
