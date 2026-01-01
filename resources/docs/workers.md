# Background Workers

## Supervisor Programs

Supervisor manages long-running processes like queue workers.

**What is Supervisor?**
Supervisor is a process control system that:
- Starts processes automatically on boot
- Restarts crashed processes
- Manages process groups
- Provides logging and monitoring

**Creating a Worker:**
1. Navigate to "Workers" → "Create Worker"
2. Select the server
3. Enter the command to run
4. Configure process count and options
5. Save and start the worker

**Configuration Options:**
| Option | Description |
|--------|-------------|
| Command | The command to execute |
| User | System user to run as (default: sitekit) |
| Directory | Working directory for the process |
| Processes | Number of process instances |
| Autostart | Start when supervisor starts |
| Autorestart | Restart if process exits |

---

## Queue Workers

Laravel queue workers process jobs in the background.

**Laravel Worker Command:**
```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

**Recommended Options:**
| Option | Value | Purpose |
|--------|-------|---------|
| `--sleep` | 3 | Seconds to wait when no jobs |
| `--tries` | 3 | Max attempts per job |
| `--max-time` | 3600 | Restart worker after 1 hour |
| `--timeout` | 60 | Max seconds per job |
| `--queue` | high,default | Priority queues |

**Multiple Queues:**
For priority processing, run separate workers:
```bash
# High priority
php artisan queue:work --queue=high --tries=3

# Default priority
php artisan queue:work --queue=default --tries=3

# Low priority
php artisan queue:work --queue=low --tries=3
```

---

## Horizon (Laravel)

For advanced queue management, use Laravel Horizon.

**Installation:**
```bash
composer require laravel/horizon
php artisan horizon:install
```

**Supervisor Config for Horizon:**
```ini
[program:horizon]
command=php /home/sitekit/example.com/current/artisan horizon
autostart=true
autorestart=true
user=sitekit
redirect_stderr=true
stdout_logfile=/home/sitekit/example.com/storage/logs/horizon.log
```

**Horizon Dashboard:**
Access at `yourdomain.com/horizon` (requires authentication).

---

## Managing Workers

**Worker States:**
- **Running**: Process is active and healthy
- **Stopped**: Process is not running
- **Starting**: Process is being started
- **Fatal**: Process failed to start

**Actions:**
- **Start**: Launch the worker process(es)
- **Stop**: Gracefully stop workers
- **Restart**: Stop and start workers
- **View Logs**: See worker output

**Logs Location:**
```
/var/log/supervisor/worker-name.log
/var/log/supervisor/worker-name-error.log
```

**Best Practices:**
- Use `--max-time` to prevent memory leaks
- Monitor worker health and memory usage
- Set appropriate `--tries` for job reliability
- Use separate workers for different queue priorities
- Configure proper logging for debugging
