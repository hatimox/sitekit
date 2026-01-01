<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Http;

class HealthCheck extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_UP = 'up';
    public const STATUS_DOWN = 'down';

    protected $fillable = [
        'web_app_id',
        'team_id',
        'name',
        'url',
        'method',
        'expected_status',
        'expected_content',
        'timeout_seconds',
        'interval_minutes',
        'is_enabled',
        'notify_on_failure',
        'notify_on_recovery',
        'status',
        'consecutive_failures',
        'uptime_percentage',
        'last_checked_at',
        'last_response_time_ms',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'notify_on_failure' => 'boolean',
            'notify_on_recovery' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function webApp(): BelongsTo
    {
        return $this->belongsTo(WebApp::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HealthCheckLog::class)->orderByDesc('checked_at');
    }

    public function check(): array
    {
        $startTime = microtime(true);
        $result = [
            'status' => self::STATUS_DOWN,
            'response_time_ms' => null,
            'status_code' => null,
            'error' => null,
        ];

        try {
            $response = Http::timeout($this->timeout_seconds)
                ->withOptions(['verify' => false])
                ->{strtolower($this->method)}($this->url);

            $result['response_time_ms'] = (int)((microtime(true) - $startTime) * 1000);
            $result['status_code'] = $response->status();

            // Check status code
            if ($response->status() !== $this->expected_status) {
                $result['error'] = "Expected status {$this->expected_status}, got {$response->status()}";
                return $result;
            }

            // Check expected content if specified
            if ($this->expected_content && !str_contains($response->body(), $this->expected_content)) {
                $result['error'] = "Expected content not found in response";
                return $result;
            }

            $result['status'] = self::STATUS_UP;

        } catch (\Exception $e) {
            $result['response_time_ms'] = (int)((microtime(true) - $startTime) * 1000);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    public function performCheck(): HealthCheckLog
    {
        $result = $this->check();
        $previousStatus = $this->status;

        // Create log entry
        $log = HealthCheckLog::create([
            'health_check_id' => $this->id,
            'status' => $result['status'],
            'response_time_ms' => $result['response_time_ms'],
            'status_code' => $result['status_code'],
            'error' => $result['error'],
            'checked_at' => now(),
        ]);

        // Update health check status
        if ($result['status'] === self::STATUS_UP) {
            $wasDown = $this->status === self::STATUS_DOWN;

            $this->update([
                'status' => self::STATUS_UP,
                'consecutive_failures' => 0,
                'last_checked_at' => now(),
                'last_response_time_ms' => $result['response_time_ms'],
                'last_error' => null,
            ]);

            // Notify on recovery
            if ($wasDown && $this->notify_on_recovery) {
                $this->sendRecoveryNotification();
            }
        } else {
            $consecutiveFailures = $this->consecutive_failures + 1;

            $this->update([
                'status' => self::STATUS_DOWN,
                'consecutive_failures' => $consecutiveFailures,
                'last_checked_at' => now(),
                'last_response_time_ms' => $result['response_time_ms'],
                'last_error' => $result['error'],
            ]);

            // Notify on failure (after 2 consecutive failures to avoid false positives)
            if ($consecutiveFailures === 2 && $this->notify_on_failure) {
                $this->sendFailureNotification();
            }
        }

        // Update uptime percentage
        $this->updateUptimePercentage();

        return $log;
    }

    public function updateUptimePercentage(): void
    {
        $last24Hours = $this->logs()
            ->where('checked_at', '>=', now()->subDay())
            ->get();

        if ($last24Hours->isEmpty()) {
            return;
        }

        $upCount = $last24Hours->where('status', self::STATUS_UP)->count();
        $percentage = (int)(($upCount / $last24Hours->count()) * 100);

        $this->update(['uptime_percentage' => $percentage]);
    }

    protected function sendFailureNotification(): void
    {
        $owner = $this->team->owner;
        if ($owner) {
            // TODO: Implement notification (email/Slack)
            // $owner->notify(new HealthCheckFailed($this));
        }
    }

    protected function sendRecoveryNotification(): void
    {
        $owner = $this->team->owner;
        if ($owner) {
            // TODO: Implement notification (email/Slack)
            // $owner->notify(new HealthCheckRecovered($this));
        }
    }

    public function isUp(): bool
    {
        return $this->status === self::STATUS_UP;
    }

    public function isDown(): bool
    {
        return $this->status === self::STATUS_DOWN;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_UP => 'success',
            self::STATUS_DOWN => 'danger',
            default => 'warning',
        };
    }
}
