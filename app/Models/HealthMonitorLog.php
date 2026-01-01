<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthMonitorLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'health_monitor_id',
        'status',
        'response_time_ms',
        'status_code',
        'error',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'response_time_ms' => 'float',
        ];
    }

    public function healthMonitor(): BelongsTo
    {
        return $this->belongsTo(HealthMonitor::class);
    }

    public function isUp(): bool
    {
        return $this->status === 'up';
    }

    public function isDown(): bool
    {
        return $this->status === 'down';
    }
}
