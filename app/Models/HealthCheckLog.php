<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthCheckLog extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'health_check_id',
        'status',
        'response_time_ms',
        'status_code',
        'error',
        'checked_from',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public function healthCheck(): BelongsTo
    {
        return $this->belongsTo(HealthCheck::class);
    }

    public function isUp(): bool
    {
        return $this->status === HealthCheck::STATUS_UP;
    }

    public function isDown(): bool
    {
        return $this->status === HealthCheck::STATUS_DOWN;
    }
}
