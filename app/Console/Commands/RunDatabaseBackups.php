<?php

namespace App\Console\Commands;

use App\Models\Database;
use App\Models\DatabaseBackup;
use Cron\CronExpression;
use Illuminate\Console\Command;

class RunDatabaseBackups extends Command
{
    protected $signature = 'databases:backup';

    protected $description = 'Run scheduled database backups';

    public function handle(): int
    {
        $databases = Database::where('backup_enabled', true)
            ->whereNotNull('backup_schedule')
            ->where('status', Database::STATUS_ACTIVE)
            ->get();

        $this->info("Checking {$databases->count()} databases for scheduled backups...");

        $backupsCreated = 0;

        foreach ($databases as $database) {
            if ($this->shouldRunBackup($database)) {
                $this->createBackup($database);
                $backupsCreated++;
            }
        }

        $this->info("Created {$backupsCreated} backup(s).");

        // Cleanup old backups
        $this->cleanupOldBackups();

        return self::SUCCESS;
    }

    protected function shouldRunBackup(Database $database): bool
    {
        // Check if a backup is already in progress
        $hasActiveBackup = $database->backups()
            ->whereIn('status', [DatabaseBackup::STATUS_PENDING, DatabaseBackup::STATUS_RUNNING])
            ->exists();

        if ($hasActiveBackup) {
            $this->line("  Skipping {$database->name}: backup already in progress");
            return false;
        }

        // Parse cron expression
        $cron = new CronExpression($database->backup_schedule);

        // Check if we're within the scheduled minute
        if (!$cron->isDue()) {
            return false;
        }

        // Avoid running backup if one was run in the last 55 minutes (debounce)
        $recentBackup = $database->backups()
            ->where('created_at', '>=', now()->subMinutes(55))
            ->exists();

        if ($recentBackup) {
            return false;
        }

        return true;
    }

    protected function createBackup(Database $database): void
    {
        $this->line("  Creating backup for: {$database->name}");

        DatabaseBackup::createBackup($database, DatabaseBackup::TRIGGER_SCHEDULED);
    }

    protected function cleanupOldBackups(): void
    {
        $databases = Database::where('backup_enabled', true)
            ->where('backup_retention_days', '>', 0)
            ->get();

        $totalDeleted = 0;

        foreach ($databases as $database) {
            $deleted = $database->cleanupOldBackups();
            $totalDeleted += $deleted;

            if ($deleted > 0) {
                $this->line("  Cleaned up {$deleted} old backup(s) for {$database->name}");
            }
        }

        if ($totalDeleted > 0) {
            $this->info("Total backups cleaned up: {$totalDeleted}");
        }
    }
}
