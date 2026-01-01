<?php

namespace App\Models;

use App\Models\Concerns\HasErrorTracking;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Database extends Model
{
    use HasFactory, HasUuids, LogsActivity, HasErrorTracking;

    public const TYPE_MYSQL = 'mysql';
    public const TYPE_MARIADB = 'mariadb';
    public const TYPE_POSTGRESQL = 'postgresql';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'server_id',
        'team_id',
        'web_app_id',
        'name',
        'type',
        'status',
        'error_message',
        'last_error',
        'last_error_at',
        'suggested_action',
        'backup_enabled',
        'backup_schedule',
        'backup_retention_days',
        'last_backup_at',
    ];

    protected function casts(): array
    {
        return [
            'backup_enabled' => 'boolean',
            'backup_retention_days' => 'integer',
            'last_backup_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

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

    public function users(): HasMany
    {
        return $this->hasMany(DatabaseUser::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(DatabaseBackup::class);
    }

    public function getHostAttribute(): string
    {
        return '127.0.0.1';
    }

    public function getPortAttribute(): int
    {
        return match ($this->type) {
            self::TYPE_POSTGRESQL => 5432,
            default => 3306,
        };
    }

    public function getConnectionStringAttribute(): string
    {
        $driver = match ($this->type) {
            self::TYPE_POSTGRESQL => 'postgresql',
            default => 'mysql',
        };

        return "{$driver}://[username]:[password]@{$this->host}:{$this->port}/{$this->name}";
    }

    public function getConnectionCommandAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_POSTGRESQL => "psql -h {$this->host} -p {$this->port} -U [username] {$this->name}",
            default => "mysql -h {$this->host} -P {$this->port} -u [username] -p {$this->name}",
        };
    }

    public function dispatchJob(string $type, array $payload = []): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->team_id,
            'type' => $type,
            'payload' => array_merge(['database_id' => $this->id], $payload),
        ]);
    }

    public function createBackup(): DatabaseBackup
    {
        return DatabaseBackup::createBackup($this, DatabaseBackup::TRIGGER_MANUAL);
    }

    public function latestBackup(): ?DatabaseBackup
    {
        return $this->backups()->latest()->first();
    }

    public function latestCompletedBackup(): ?DatabaseBackup
    {
        return $this->backups()
            ->where('status', DatabaseBackup::STATUS_COMPLETED)
            ->latest()
            ->first();
    }

    public function cleanupOldBackups(): int
    {
        if (!$this->backup_retention_days) {
            return 0;
        }

        $cutoff = now()->subDays($this->backup_retention_days);

        return $this->backups()
            ->where('status', DatabaseBackup::STATUS_COMPLETED)
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
