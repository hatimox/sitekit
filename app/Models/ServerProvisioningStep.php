<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerProvisioningStep extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_WEB_SERVER = 'web_server';
    public const CATEGORY_PHP = 'php';
    public const CATEGORY_DATABASE = 'database';
    public const CATEGORY_CACHE = 'cache';
    public const CATEGORY_TOOLS = 'tools';

    protected $fillable = [
        'server_id',
        'step_type',
        'step_name',
        'category',
        'order',
        'status',
        'is_required',
        'is_default',
        'configuration',
        'agent_job_id',
        'output',
        'error_message',
        'exit_code',
        'started_at',
        'completed_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_default' => 'boolean',
            'configuration' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_seconds' => 'integer',
            'exit_code' => 'integer',
        ];
    }

    /**
     * Get formatted duration for display.
     */
    public function getFormattedDuration(): ?string
    {
        if ($this->duration_seconds === null) {
            return null;
        }

        if ($this->duration_seconds < 60) {
            return $this->duration_seconds . 's';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        if ($minutes < 60) {
            return $minutes . 'm ' . $seconds . 's';
        }

        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;

        return $hours . 'h ' . $minutes . 'm';
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function agentJob(): BelongsTo
    {
        return $this->belongsTo(AgentJob::class);
    }

    /**
     * Get the default provisioning steps for a new server.
     */
    public static function getDefaultSteps(): array
    {
        return [
            // System
            [
                'step_type' => 'provision_system',
                'step_name' => 'System Updates & Security',
                'category' => self::CATEGORY_SYSTEM,
                'order' => 1,
                'is_required' => true,
                'is_default' => true,
                'configuration' => ['includes' => ['apt_update', 'fail2ban', 'ufw']],
            ],

            // Web Server (Nginx is default, Apache is optional)
            [
                'step_type' => 'provision_nginx',
                'step_name' => 'Nginx',
                'category' => self::CATEGORY_WEB_SERVER,
                'order' => 10,
                'is_required' => false,
                'is_default' => true,
                'configuration' => [],
            ],
            [
                'step_type' => 'provision_apache',
                'step_name' => 'Apache 2.4',
                'category' => self::CATEGORY_WEB_SERVER,
                'order' => 11,
                'is_required' => false,
                'is_default' => true, // Installed but disabled (Nginx is default)
                'configuration' => [],
            ],

            // PHP versions (8.4 is the default for new web apps)
            [
                'step_type' => 'provision_php',
                'step_name' => 'PHP 8.5',
                'category' => self::CATEGORY_PHP,
                'order' => 20,
                'is_required' => false,
                'is_default' => true,
                'configuration' => ['version' => '8.5'],
            ],
            [
                'step_type' => 'provision_php',
                'step_name' => 'PHP 8.4',
                'category' => self::CATEGORY_PHP,
                'order' => 21,
                'is_required' => false,
                'is_default' => true,
                'configuration' => ['version' => '8.4', 'is_default' => true],
            ],
            [
                'step_type' => 'provision_php',
                'step_name' => 'PHP 8.3',
                'category' => self::CATEGORY_PHP,
                'order' => 22,
                'is_required' => false,
                'is_default' => true,
                'configuration' => ['version' => '8.3'],
            ],
            [
                'step_type' => 'provision_php',
                'step_name' => 'PHP 8.2',
                'category' => self::CATEGORY_PHP,
                'order' => 23,
                'is_required' => false,
                'is_default' => true,
                'configuration' => ['version' => '8.2'],
            ],
            [
                'step_type' => 'provision_php',
                'step_name' => 'PHP 8.1',
                'category' => self::CATEGORY_PHP,
                'order' => 24,
                'is_required' => false,
                'is_default' => true,
                'configuration' => ['version' => '8.1'],
            ],

            // Databases (MariaDB runs by default, MySQL installed but stopped)
            [
                'step_type' => 'provision_mariadb',
                'step_name' => 'MariaDB 11.4',
                'category' => self::CATEGORY_DATABASE,
                'order' => 30,
                'is_required' => false,
                'is_default' => true,
                'configuration' => ['version' => '11.4'], // LTS until 2029
            ],
            [
                'step_type' => 'provision_mysql',
                'step_name' => 'MySQL 8.4',
                'category' => self::CATEGORY_DATABASE,
                'order' => 31,
                'is_required' => false,
                'is_default' => false, // NOT installed by default (conflicts with MariaDB packages)
                'configuration' => [
                    'version' => '8.4', // LTS until 2032
                ],
            ],
            [
                'step_type' => 'provision_postgresql',
                'step_name' => 'PostgreSQL 17',
                'category' => self::CATEGORY_DATABASE,
                'order' => 32,
                'is_required' => false,
                'is_default' => false,
                'configuration' => ['version' => '17'],
            ],

            // Cache
            [
                'step_type' => 'provision_redis',
                'step_name' => 'Redis',
                'category' => self::CATEGORY_CACHE,
                'order' => 40,
                'is_required' => false,
                'is_default' => true,
                'configuration' => [],
            ],
            [
                'step_type' => 'provision_memcached',
                'step_name' => 'Memcached',
                'category' => self::CATEGORY_CACHE,
                'order' => 41,
                'is_required' => false,
                'is_default' => false,
                'configuration' => [],
            ],

            // Tools
            [
                'step_type' => 'provision_composer',
                'step_name' => 'Composer',
                'category' => self::CATEGORY_TOOLS,
                'order' => 50,
                'is_required' => false,
                'is_default' => true,
                'configuration' => [],
            ],
            [
                'step_type' => 'provision_node',
                'step_name' => 'Node.js 24',
                'category' => self::CATEGORY_TOOLS,
                'order' => 51,
                'is_required' => false,
                'is_default' => true,
                'configuration' => ['version' => '24'], // Active LTS
            ],
            [
                'step_type' => 'provision_supervisor',
                'step_name' => 'Supervisor',
                'category' => self::CATEGORY_TOOLS,
                'order' => 52,
                'is_required' => false,
                'is_default' => true,
                'configuration' => [],
            ],
        ];
    }

    /**
     * Get friendly category name.
     */
    public function getCategoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_SYSTEM => 'System',
            self::CATEGORY_WEB_SERVER => 'Web Server',
            self::CATEGORY_PHP => 'PHP',
            self::CATEGORY_DATABASE => 'Database',
            self::CATEGORY_CACHE => 'Cache',
            self::CATEGORY_TOOLS => 'Tools',
            default => ucfirst($this->category),
        };
    }

    /**
     * Get status icon for display.
     */
    public function getStatusIcon(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'heroicon-o-check-circle',
            self::STATUS_IN_PROGRESS => 'heroicon-o-arrow-path',
            self::STATUS_QUEUED => 'heroicon-o-clock',
            self::STATUS_FAILED => 'heroicon-o-x-circle',
            self::STATUS_SKIPPED => 'heroicon-o-minus-circle',
            default => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }

    /**
     * Get status color for display.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'success',
            self::STATUS_IN_PROGRESS => 'info',
            self::STATUS_QUEUED => 'warning',
            self::STATUS_FAILED => 'danger',
            self::STATUS_SKIPPED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Dispatch an agent job for this step.
     */
    public function dispatchJob(): ?AgentJob
    {
        if (!$this->server) {
            return null;
        }

        $payload = $this->configuration ?? [];
        $payload['step_id'] = $this->id;

        $job = $this->server->dispatchJob($this->step_type, $payload, priority: 3);

        $this->update([
            'status' => self::STATUS_QUEUED,
            'agent_job_id' => $job->id,
        ]);

        return $job;
    }

    /**
     * Mark step as started.
     */
    public function markStarted(): void
    {
        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark step as completed.
     */
    public function markCompleted(?string $output = null, int $exitCode = 0): void
    {
        $startedAt = $this->started_at ?? now();
        $duration = $startedAt->diffInSeconds(now());

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'output' => $output,
            'exit_code' => $exitCode,
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Mark step as failed.
     */
    public function markFailed(string $error, ?string $output = null, int $exitCode = 1): void
    {
        $startedAt = $this->started_at ?? now();
        $duration = $startedAt->diffInSeconds(now());

        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
            'output' => $output,
            'exit_code' => $exitCode,
            'completed_at' => now(),
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Mark step as skipped.
     */
    public function markSkipped(): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if step can be retried.
     */
    public function canRetry(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_SKIPPED]);
    }

    /**
     * Check if step can be skipped.
     */
    public function canSkip(): bool
    {
        return !$this->is_required && in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_QUEUED,
            self::STATUS_FAILED,
        ]);
    }

    /**
     * Retry a failed step.
     */
    public function retry(): ?AgentJob
    {
        if (!$this->canRetry()) {
            return null;
        }

        $this->update([
            'status' => self::STATUS_PENDING,
            'output' => null,
            'error_message' => null,
            'exit_code' => null,
            'started_at' => null,
            'completed_at' => null,
            'duration_seconds' => null,
            'agent_job_id' => null,
        ]);

        return $this->dispatchJob();
    }
}
