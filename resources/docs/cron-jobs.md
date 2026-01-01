# Cron Jobs

## Cron Schedule Format

The cron schedule uses 5 fields separated by spaces:

```
┌───────────── minute (0-59)
│ ┌───────────── hour (0-23)
│ │ ┌───────────── day of month (1-31)
│ │ │ ┌───────────── month (1-12)
│ │ │ │ ┌───────────── day of week (0-6, Sunday=0)
│ │ │ │ │
* * * * *
```

**Special Characters:**
- `*` - any value
- `,` - list separator (e.g., `1,15` = 1st and 15th)
- `-` - range (e.g., `1-5` = Monday to Friday)
- `/` - step (e.g., `*/5` = every 5 minutes)

**Field Values:**
| Field | Range | Special |
|-------|-------|---------|
| Minute | 0-59 | * , - / |
| Hour | 0-23 | * , - / |
| Day of Month | 1-31 | * , - / |
| Month | 1-12 | * , - / |
| Day of Week | 0-6 | * , - / |

---

## Common Examples

| Schedule | Meaning |
|----------|---------|
| `* * * * *` | Every minute |
| `*/5 * * * *` | Every 5 minutes |
| `*/15 * * * *` | Every 15 minutes |
| `0 * * * *` | Every hour (at minute 0) |
| `0 0 * * *` | Daily at midnight |
| `0 0 * * 0` | Weekly on Sunday at midnight |
| `0 0 1 * *` | Monthly on the 1st at midnight |
| `0 3 * * *` | Daily at 3:00 AM |
| `30 4 * * 1-5` | Weekdays at 4:30 AM |
| `0 9-17 * * 1-5` | Hourly 9 AM-5 PM on weekdays |
| `0 0 1,15 * *` | 1st and 15th of each month |

---

## Laravel Scheduler

For Laravel applications, use the scheduler instead of individual cron jobs:

**Single Cron Entry:**
```
* * * * * cd /home/sitekit/example.com/current && php artisan schedule:run >> /dev/null 2>&1
```

**Define Tasks in Laravel:**
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('backup:run')->daily();
    $schedule->command('cache:clear')->weekly();
    $schedule->job(new ProcessReports)->hourly();
}
```

---

## Best Practices

**Logging:**
Redirect output to log files for debugging:
```bash
/path/to/command >> /var/log/cron.log 2>&1
```

**Avoid Overlapping:**
For long-running tasks, use lock files or Laravel's `withoutOverlapping()`:
```php
$schedule->command('long:task')->hourly()->withoutOverlapping();
```

**Timezone:**
Cron jobs run in the server's timezone. Use UTC for consistency across servers.

**Testing:**
Test cron commands manually before scheduling:
```bash
cd /home/sitekit/example.com/current
php artisan your:command
```
