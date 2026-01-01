<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class HealthMonitor extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    public const TYPE_HTTP = 'http';
    public const TYPE_HTTPS = 'https';
    public const TYPE_TCP = 'tcp';
    public const TYPE_PING = 'ping';
    public const TYPE_HEARTBEAT = 'heartbeat';
    public const TYPE_SSL_EXPIRY = 'ssl_expiry';

    public const STATUS_UP = 'up';
    public const STATUS_DOWN = 'down';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'team_id',
        'server_id',
        'web_app_id',
        'type',
        'name',
        'url',
        'host',
        'port',
        'heartbeat_token',
        'interval_seconds',
        'timeout_seconds',
        'failure_threshold',
        'recovery_threshold',
        'status',
        'last_check_at',
        'last_up_at',
        'last_down_at',
        'consecutive_failures',
        'consecutive_successes',
        'is_active',
        'is_up',
        'last_response_time',
        'last_status_code',
        'last_error',
        'settings',
        'uptime_24h',
        'uptime_7d',
        'uptime_30d',
        'avg_response_time',
    ];

    protected function casts(): array
    {
        return [
            'last_check_at' => 'datetime',
            'last_up_at' => 'datetime',
            'last_down_at' => 'datetime',
            'is_active' => 'boolean',
            'is_up' => 'boolean',
            'settings' => 'array',
            'last_response_time' => 'float',
            'uptime_24h' => 'decimal:2',
            'uptime_7d' => 'decimal:2',
            'uptime_30d' => 'decimal:2',
            'avg_response_time' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HealthMonitor $monitor) {
            if ($monitor->type === self::TYPE_HEARTBEAT && empty($monitor->heartbeat_token)) {
                $monitor->heartbeat_token = Str::random(32);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function webApp(): BelongsTo
    {
        return $this->belongsTo(WebApp::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HealthMonitorLog::class)->orderBy('checked_at', 'desc');
    }

    /**
     * Log a check result.
     */
    public function logCheck(bool $success, ?float $responseTime = null, ?int $statusCode = null, ?string $error = null): HealthMonitorLog
    {
        return $this->logs()->create([
            'status' => $success ? 'up' : 'down',
            'response_time_ms' => $responseTime,
            'status_code' => $statusCode,
            'error' => $error,
            'checked_at' => now(),
        ]);
    }

    /**
     * Calculate and update uptime percentages.
     */
    public function updateUptimeStats(): void
    {
        $this->update([
            'uptime_24h' => $this->calculateUptime(24),
            'uptime_7d' => $this->calculateUptime(24 * 7),
            'uptime_30d' => $this->calculateUptime(24 * 30),
            'avg_response_time' => $this->calculateAverageResponseTime(24),
        ]);
    }

    /**
     * Calculate uptime percentage for the given hours.
     */
    public function calculateUptime(int $hours): ?float
    {
        $since = now()->subHours($hours);

        $total = $this->logs()
            ->where('checked_at', '>=', $since)
            ->count();

        if ($total === 0) {
            return null;
        }

        $upCount = $this->logs()
            ->where('checked_at', '>=', $since)
            ->where('status', 'up')
            ->count();

        return round(($upCount / $total) * 100, 2);
    }

    /**
     * Calculate average response time for the given hours.
     */
    public function calculateAverageResponseTime(int $hours): ?float
    {
        $since = now()->subHours($hours);

        return $this->logs()
            ->where('checked_at', '>=', $since)
            ->where('status', 'up')
            ->whereNotNull('response_time_ms')
            ->avg('response_time_ms');
    }

    /**
     * Get response time data for charting.
     */
    public function getResponseTimeChartData(int $hours = 24): array
    {
        $since = now()->subHours($hours);

        return $this->logs()
            ->where('checked_at', '>=', $since)
            ->where('status', 'up')
            ->whereNotNull('response_time_ms')
            ->orderBy('checked_at')
            ->get(['response_time_ms', 'checked_at'])
            ->map(fn ($log) => [
                'time' => $log->checked_at->format('H:i'),
                'value' => round($log->response_time_ms, 2),
            ])
            ->toArray();
    }

    /**
     * Get status history for charting (uptime over time).
     */
    public function getStatusHistoryData(int $hours = 24): array
    {
        $since = now()->subHours($hours);

        return $this->logs()
            ->where('checked_at', '>=', $since)
            ->orderBy('checked_at')
            ->get(['status', 'checked_at'])
            ->map(fn ($log) => [
                'time' => $log->checked_at->format('H:i'),
                'status' => $log->status,
                'value' => $log->status === 'up' ? 1 : 0,
            ])
            ->toArray();
    }

    /**
     * Get hourly uptime for the last N hours.
     */
    public function getHourlyUptime(int $hours = 24): array
    {
        $result = [];

        for ($i = $hours - 1; $i >= 0; $i--) {
            $hourStart = now()->subHours($i)->startOfHour();
            $hourEnd = $hourStart->copy()->endOfHour();

            $checks = $this->logs()
                ->whereBetween('checked_at', [$hourStart, $hourEnd])
                ->get();

            $total = $checks->count();
            $upCount = $checks->where('status', 'up')->count();

            $result[] = [
                'hour' => $hourStart->format('M d H:00'),
                'uptime' => $total > 0 ? round(($upCount / $total) * 100, 1) : null,
                'checks' => $total,
            ];
        }

        return $result;
    }

    /**
     * Prune old logs (keep last 30 days).
     */
    public function pruneOldLogs(int $days = 30): int
    {
        return $this->logs()
            ->where('checked_at', '<', now()->subDays($days))
            ->delete();
    }

    public function isUp(): bool
    {
        return $this->status === self::STATUS_UP;
    }

    public function isDown(): bool
    {
        return $this->status === self::STATUS_DOWN;
    }

    public function markUp(): void
    {
        $this->update([
            'status' => self::STATUS_UP,
            'is_up' => true,
            'last_check_at' => now(),
            'last_up_at' => now(),
            'consecutive_failures' => 0,
            'consecutive_successes' => $this->consecutive_successes + 1,
        ]);
    }

    public function markDown(?string $error = null): void
    {
        $this->increment('consecutive_failures');
        $this->update([
            'last_check_at' => now(),
            'consecutive_successes' => 0,
            'last_error' => $error,
        ]);

        if ($this->consecutive_failures >= $this->failure_threshold) {
            $this->update([
                'status' => self::STATUS_DOWN,
                'is_up' => false,
                'last_down_at' => now(),
            ]);
        }
    }

    public function getHeartbeatUrlAttribute(): ?string
    {
        if ($this->type !== self::TYPE_HEARTBEAT || !$this->heartbeat_token) {
            return null;
        }

        return route('api.heartbeat.ping', ['token' => $this->heartbeat_token]);
    }

    public function getCheckTargetAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_HTTP => $this->url,
            self::TYPE_TCP => "{$this->host}:{$this->port}",
            self::TYPE_HEARTBEAT => $this->heartbeat_url,
            default => '',
        };
    }

    public function needsCheck(): bool
    {
        if (!$this->is_active || $this->status === self::STATUS_PAUSED) {
            return false;
        }

        if ($this->last_check_at === null) {
            return true;
        }

        return $this->last_check_at->addSeconds($this->interval_seconds)->isPast();
    }
}
