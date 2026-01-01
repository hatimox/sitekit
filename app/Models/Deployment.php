<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deployment extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_WEBHOOK = 'webhook';
    public const TRIGGER_ROLLBACK = 'rollback';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CLONING = 'cloning';
    public const STATUS_BUILDING = 'building';
    public const STATUS_DEPLOYING = 'deploying';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'web_app_id',
        'team_id',
        'user_id',
        'source_provider_id',
        'repository',
        'branch',
        'commit_hash',
        'commit_message',
        'trigger',
        'status',
        'release_path',
        'build_output',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceProvider(): BelongsTo
    {
        return $this->belongsTo(SourceProvider::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CLONING,
            self::STATUS_BUILDING,
            self::STATUS_DEPLOYING,
        ]);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        $end = $this->finished_at ?? now();

        return $this->started_at->diffInSeconds($end);
    }

    public function getFormattedDurationAttribute(): string
    {
        $duration = $this->duration;

        if ($duration === null) {
            return '-';
        }

        if ($duration < 60) {
            return "{$duration}s";
        }

        $minutes = floor($duration / 60);
        $seconds = $duration % 60;

        return "{$minutes}m {$seconds}s";
    }

    public function getShortCommitHashAttribute(): string
    {
        return $this->commit_hash ? substr($this->commit_hash, 0, 7) : '-';
    }

    public function appendLog(string $line): void
    {
        $this->update([
            'build_output' => ($this->build_output ?? '') . $line,
        ]);
    }

    public function markAs(string $status, ?string $error = null): void
    {
        $data = ['status' => $status];

        if ($status === self::STATUS_CLONING && $this->started_at === null) {
            $data['started_at'] = now();
        }

        if (in_array($status, [self::STATUS_ACTIVE, self::STATUS_FAILED, self::STATUS_ROLLED_BACK])) {
            $data['finished_at'] = now();
        }

        if ($error !== null) {
            $data['error'] = $error;
        }

        $this->update($data);
    }

    public function dispatchJob(): AgentJob
    {
        $webApp = $this->webApp;

        return AgentJob::create([
            'server_id' => $webApp->server_id,
            'team_id' => $this->team_id,
            'type' => 'deploy',
            'payload' => [
                'deployment_id' => $this->id,
                'app_path' => $webApp->root_path,
                'username' => $webApp->system_user,
                'repository' => $this->repository,
                'branch' => $this->branch,
                'commit_hash' => $this->commit_hash,
                'ssh_url' => $this->getGitSshUrl(),
                'deploy_key' => $webApp->deploy_private_key,
                'shared_files' => $webApp->shared_files ?? ['.env'],
                'shared_directories' => $webApp->shared_directories ?? ['storage'],
                'build_script' => $webApp->deploy_script,
                'php_version' => $webApp->php_version,
            ],
        ]);
    }

    protected function getGitSshUrl(): string
    {
        $provider = $this->sourceProvider?->provider ?? 'github';

        return match ($provider) {
            'github' => "git@github.com:{$this->repository}.git",
            'gitlab' => "git@gitlab.com:{$this->repository}.git",
            'bitbucket' => "git@bitbucket.org:{$this->repository}.git",
            default => "git@github.com:{$this->repository}.git",
        };
    }
}
