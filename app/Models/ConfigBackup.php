<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigBackup extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'service_id',
        'user_id',
        'config_type',
        'file_path',
        'content',
        'reason',
        'is_auto',
    ];

    protected function casts(): array
    {
        return [
            'is_auto' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create an auto-backup before editing a config file.
     */
    public static function createAutoBackup(Service $service, string $configType, string $filePath, string $content): static
    {
        return static::create([
            'service_id' => $service->id,
            'user_id' => auth()->id(),
            'config_type' => $configType,
            'file_path' => $filePath,
            'content' => $content,
            'reason' => 'Auto-backup before edit',
            'is_auto' => true,
        ]);
    }

    /**
     * Get the latest backup for a specific config file.
     */
    public static function getLatestForFile(string $serviceId, string $filePath): ?static
    {
        return static::where('service_id', $serviceId)
            ->where('file_path', $filePath)
            ->latest()
            ->first();
    }

    /**
     * Get config type label for display.
     */
    public function getConfigTypeLabelAttribute(): string
    {
        return match ($this->config_type) {
            'nginx' => 'Nginx',
            'php-fpm' => 'PHP-FPM',
            'mysql' => 'MySQL/MariaDB',
            'postgresql' => 'PostgreSQL',
            'redis' => 'Redis',
            'supervisor' => 'Supervisor',
            default => ucfirst($this->config_type),
        };
    }
}
