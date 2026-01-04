<?php

use Illuminate\Support\Facades\Schedule;

// Health monitor checks - every minute
Schedule::command('monitors:check')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Node.js app health checks - every minute
Schedule::command('node:health-check')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Server heartbeat checks - every 5 minutes
Schedule::command('servers:check-heartbeats')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Firewall rule rollback - every minute (for safety confirmation timeout)
Schedule::command('firewall:rollback-expired')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// SSL certificate renewal - daily at 3am
Schedule::command('ssl:renew')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Cleanup old stats - daily at 4am
Schedule::command('cleanup:stats')
    ->dailyAt('04:00')
    ->withoutOverlapping();

// Prune old service stats - daily at 4:15am
Schedule::command('service-stats:prune')
    ->dailyAt('04:15')
    ->withoutOverlapping();

// Database backups - every minute (cron-based scheduling)
Schedule::command('databases:backup')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
