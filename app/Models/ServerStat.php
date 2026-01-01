<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerStat extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'server_id',
        'cpu_percent',
        'memory_percent',
        'memory_used_bytes',
        'memory_total_bytes',
        'disk_percent',
        'disk_used_bytes',
        'disk_total_bytes',
        'load_1m',
        'load_5m',
        'load_15m',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'cpu_percent' => 'float',
            'memory_percent' => 'float',
            'disk_percent' => 'float',
            'load_1m' => 'float',
            'load_5m' => 'float',
            'load_15m' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function getMemoryUsedFormattedAttribute(): string
    {
        return $this->formatBytes($this->memory_used_bytes);
    }

    public function getMemoryTotalFormattedAttribute(): string
    {
        return $this->formatBytes($this->memory_total_bytes);
    }

    public function getDiskUsedFormattedAttribute(): string
    {
        return $this->formatBytes($this->disk_used_bytes);
    }

    public function getDiskTotalFormattedAttribute(): string
    {
        return $this->formatBytes($this->disk_total_bytes);
    }

    protected function formatBytes(?int $bytes): string
    {
        if ($bytes === null || $bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));

        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Check if disk usage exceeds the alert threshold.
     */
    public function isDiskAlert(?float $threshold = null): bool
    {
        $threshold = $threshold ?? $this->server?->alert_disk_threshold ?? 90;
        return $this->disk_percent >= $threshold;
    }

    /**
     * Check if memory usage exceeds the alert threshold.
     */
    public function isMemoryAlert(?float $threshold = null): bool
    {
        $threshold = $threshold ?? $this->server?->alert_memory_threshold ?? 90;
        return $this->memory_percent >= $threshold;
    }

    /**
     * Check if load average exceeds the alert threshold.
     */
    public function isLoadAlert(?float $threshold = null): bool
    {
        $threshold = $threshold ?? $this->server?->getEffectiveLoadThreshold() ?? 5;
        return $this->load_1m >= $threshold;
    }

    /**
     * Check if any metric exceeds its alert threshold.
     */
    public function hasAnyAlert(): bool
    {
        return $this->isDiskAlert() || $this->isMemoryAlert() || $this->isLoadAlert();
    }

    /**
     * Check if all metrics are within normal thresholds.
     */
    public function isAllNormal(): bool
    {
        return !$this->hasAnyAlert();
    }

    // Legacy methods for backwards compatibility
    public function isDiskCritical(): bool
    {
        return $this->disk_percent >= 95;
    }

    public function isDiskWarning(): bool
    {
        return $this->disk_percent >= 90;
    }

    public function isMemoryCritical(): bool
    {
        return $this->memory_percent >= 95;
    }

    public function isMemoryWarning(): bool
    {
        return $this->memory_percent >= 90;
    }

    public function isCpuHigh(): bool
    {
        return $this->cpu_percent >= 90;
    }
}
