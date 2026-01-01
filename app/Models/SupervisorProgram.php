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
