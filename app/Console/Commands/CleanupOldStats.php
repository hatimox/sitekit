<?php

namespace App\Console\Commands;

use App\Models\ServerStat;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class CleanupOldStats extends Command
{
    protected $signature = 'cleanup:stats {--days=30 : Days of stats to keep}';

    protected $description = 'Clean up old server stats and activity logs';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        // Clean up server stats
        $statsDeleted = ServerStat::where('recorded_at', '<', $cutoff)->delete();
        $this->info("Deleted {$statsDeleted} server stat records older than {$days} days.");

        // Clean up old activity logs (keep for 90 days)
        $activityDeleted = Activity::where('created_at', '<', now()->subDays(90))->delete();
        $this->info("Deleted {$activityDeleted} activity log records older than 90 days.");

        return self::SUCCESS;
    }
}
