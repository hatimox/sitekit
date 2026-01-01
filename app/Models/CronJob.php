<?php

namespace App\Models;

use App\Models\Concerns\HasErrorTracking;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronJob extends Model
{
    use HasFactory, HasUuids, LogsActivity, HasErrorTracking;

    protected $fillable = [
        'server_id',
        'team_id',
        'web_app_id',
        'name',
        'command',
        'schedule',
        'user',
        'is_active',
        'status',
        'last_error',
        'last_error_at',
        'suggested_action',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function getScheduleDescriptionAttribute(): string
    {
        return match ($this->schedule) {
            '* * * * *' => 'Every minute',
            '*/5 * * * *' => 'Every 5 minutes',
            '*/15 * * * *' => 'Every 15 minutes',
            '0 * * * *' => 'Every hour',
            '*/6 * * * *' => 'Every 6 hours',
            '0 0 * * *' => 'Daily at midnight',
            '0 0 * * 0' => 'Weekly on Sunday',
            default => $this->schedule,
        };
    }

    public function getCrontabLineAttribute(): string
    {
        return "{$this->schedule} {$this->command}";
    }

    public function syncToServer(): AgentJob
    {
        // Get all cron jobs for this user on this server
        $allCrons = CronJob::where('server_id', $this->server_id)
            ->where('user', $this->user)
            ->get();

        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->team_id,
            'type' => 'sync_crontab',
            'payload' => [
                'username' => $this->user,
                'entries' => $allCrons->map(fn ($c) => [
                    'schedule' => $c->schedule,
                    'command' => $c->command,
                    'enabled' => $c->is_active,
                ])->toArray(),
            ],
        ]);
    }

    public function runNow(): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->team_id,
            'type' => 'run_script',
            'payload' => [
                'script' => $this->command,
                'user' => $this->user,
                'cron_job_id' => $this->id,
            ],
        ]);
    }
}
