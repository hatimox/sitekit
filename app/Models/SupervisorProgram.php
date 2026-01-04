<?php

namespace App\Models;

use App\Models\Concerns\HasErrorTracking;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorProgram extends Model
{
    use HasFactory, HasUuids, HasErrorTracking;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'server_id',
        'team_id',
        'web_app_id',
        'name',
        'command',
        'directory',
        'user',
        'numprocs',
        'autostart',
        'autorestart',
        'startsecs',
        'stopwaitsecs',
        'stdout_logfile',
        'stderr_logfile',
        'environment',
        'status',
        'error_message',
        'last_error',
        'last_error_at',
        'suggested_action',
        'cpu_percent',
        'memory_mb',
        'uptime_seconds',
        'restart_count',
        'metrics_updated_at',
        'is_healthy',
        'last_health_check',
        'consecutive_failures',
        'health_check_interval',
        'health_check_url',
    ];

    protected $casts = [
        'numprocs' => 'integer',
        'autostart' => 'boolean',
        'autorestart' => 'boolean',
        'startsecs' => 'integer',
        'stopwaitsecs' => 'integer',
        'environment' => 'array',
        'cpu_percent' => 'float',
        'memory_mb' => 'integer',
        'uptime_seconds' => 'integer',
        'restart_count' => 'integer',
        'metrics_updated_at' => 'datetime',
        'last_error_at' => 'datetime',
        'is_healthy' => 'boolean',
        'last_health_check' => 'datetime',
        'consecutive_failures' => 'integer',
        'health_check_interval' => 'integer',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function webApp(): BelongsTo
    {
        return $this->belongsTo(WebApp::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isStopped(): bool
    {
        return $this->status === self::STATUS_STOPPED;
    }

    public function getUptimeFormattedAttribute(): ?string
    {
        if (!$this->uptime_seconds) {
            return null;
        }

        $seconds = $this->uptime_seconds;
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($days > 0) {
            return "{$days}d {$hours}h";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }

    public function dispatchJob(string $type, array $payload = [], int $priority = 5): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->team_id,
            'type' => $type,
            'payload' => array_merge(['program_id' => $this->id], $payload),
            'priority' => $priority,
        ]);
    }

    public function isHealthy(): bool
    {
        return $this->is_healthy;
    }

    public function needsHealthCheck(): bool
    {
        if (!$this->health_check_url) {
            return false;
        }

        if (!$this->last_health_check) {
            return true;
        }

        return $this->last_health_check->addSeconds($this->health_check_interval)->isPast();
    }

    public function markHealthy(): void
    {
        $wasUnhealthy = !$this->is_healthy;

        $this->update([
            'is_healthy' => true,
            'last_health_check' => now(),
            'consecutive_failures' => 0,
        ]);

        if ($wasUnhealthy) {
            $this->notifyRecovered();
        }
    }

    public function markUnhealthy(): void
    {
        $wasHealthy = $this->is_healthy;
        $failures = $this->consecutive_failures + 1;

        $this->update([
            'last_health_check' => now(),
            'consecutive_failures' => $failures,
        ]);

        // Mark as unhealthy after 3 consecutive failures
        if ($failures >= 3 && $wasHealthy) {
            $this->update(['is_healthy' => false]);
            $this->notifyDown();
        }
    }

    protected function notifyDown(): void
    {
        // Fire notification for Node.js app down
        if ($this->webApp && $this->team) {
            $this->team->owner?->notify(new \App\Notifications\NodeAppDown($this));
        }
    }

    protected function notifyRecovered(): void
    {
        // Fire notification for Node.js app recovered
        if ($this->webApp && $this->team) {
            $this->team->owner?->notify(new \App\Notifications\NodeAppRecovered($this));
        }
    }

    public function getHealthCheckUrlAttribute(): ?string
    {
        // Auto-generate from web app if not set
        if (!empty($this->attributes['health_check_url'])) {
            return $this->attributes['health_check_url'];
        }

        if ($this->webApp && $this->webApp->health_check_path) {
            $protocol = $this->webApp->ssl_status === 'active' ? 'https' : 'http';
            return "{$protocol}://{$this->webApp->domain}{$this->webApp->health_check_path}";
        }

        return null;
    }

    public function generateConfig(): string
    {
        $config = "[program:{$this->name}]\n";
        $config .= "command={$this->command}\n";

        if ($this->directory) {
            $config .= "directory={$this->directory}\n";
        }

        $config .= "user={$this->user}\n";
        $config .= "numprocs={$this->numprocs}\n";
        $config .= "autostart=" . ($this->autostart ? 'true' : 'false') . "\n";
        $config .= "autorestart=" . ($this->autorestart ? 'true' : 'false') . "\n";
        $config .= "startsecs={$this->startsecs}\n";
        $config .= "stopwaitsecs={$this->stopwaitsecs}\n";

        if ($this->numprocs > 1) {
            $config .= "process_name=%(program_name)s_%(process_num)02d\n";
        }

        if ($this->stdout_logfile) {
            $config .= "stdout_logfile={$this->stdout_logfile}\n";
        } else {
            $config .= "stdout_logfile=/var/log/supervisor/{$this->name}.log\n";
        }

        if ($this->stderr_logfile) {
            $config .= "stderr_logfile={$this->stderr_logfile}\n";
        } else {
            $config .= "stderr_logfile=/var/log/supervisor/{$this->name}.error.log\n";
        }

        if (!empty($this->environment)) {
            $envPairs = [];
            foreach ($this->environment as $key => $value) {
                $envPairs[] = "{$key}=\"{$value}\"";
            }
            $config .= "environment=" . implode(',', $envPairs) . "\n";
        }

        return $config;
    }
}
