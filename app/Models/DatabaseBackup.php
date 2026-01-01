<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseBackup extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_SCHEDULED = 'scheduled';

    protected $fillable = [
        'database_id',
        'server_id',
        'team_id',
        'status',
        'filename',
        'path',
        'cloud_path',
        'cloud_storage_driver',
        'size_bytes',
        'error_message',
        'trigger',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getSizeFormattedAttribute(): string
    {
        if ($this->size_bytes === null || $this->size_bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($this->size_bytes, 1024));

        return number_format($this->size_bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        $seconds = $this->completed_at->diffInSeconds($this->started_at);

        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return "{$minutes}m {$remainingSeconds}s";
    }

    public function dispatchBackupJob(): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->team_id,
            'type' => 'database_backup',
            'payload' => [
                'backup_id' => $this->id,
                'database_id' => $this->database_id,
                'database_name' => $this->database->name,
                'database_type' => $this->database->type,
                'filename' => $this->filename,
            ],
            'status' => AgentJob::STATUS_PENDING,
        ]);
    }

    public static function createBackup(Database $database, string $trigger = self::TRIGGER_MANUAL): self
    {
        $filename = sprintf(
            '%s_%s.sql.gz',
            $database->name,
            now()->format('Y-m-d_His')
        );

        $backup = self::create([
            'database_id' => $database->id,
            'server_id' => $database->server_id,
            'team_id' => $database->team_id,
            'status' => self::STATUS_PENDING,
            'filename' => $filename,
            'trigger' => $trigger,
        ]);

        $backup->dispatchBackupJob();

        return $backup;
    }

    public function hasCloudBackup(): bool
    {
        return !empty($this->cloud_path);
    }

    public function getStorageLocationAttribute(): string
    {
        if ($this->cloud_path && $this->cloud_storage_driver) {
            return match ($this->cloud_storage_driver) {
                's3' => 'Amazon S3',
                'r2' => 'Cloudflare R2',
                default => 'Cloud',
            };
        }

        return 'Server';
    }

    public function uploadToCloud(): bool
    {
        return app(\App\Services\BackupStorageService::class)->uploadToCloud($this);
    }
}
