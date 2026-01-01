<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    use HasUuids;

    protected $fillable = [
        'team_id',
        'user_id',
        'title',
        'context_type',
        'context_id',
        'messages',
        'provider',
        'model',
        'total_tokens',
    ];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'total_tokens' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class, 'conversation_id');
    }

    /**
     * Get the related resource based on context_type.
     */
    public function getContextResource(): ?Model
    {
        if (!$this->context_type || !$this->context_id) {
            return null;
        }

        $modelClass = match ($this->context_type) {
            'server' => Server::class,
            'webapp' => WebApp::class,
            'database' => Database::class,
            'service' => Service::class,
            'cronjob' => CronJob::class,
            default => null,
        };

        if (!$modelClass) {
            return null;
        }

        return $modelClass::find($this->context_id);
    }

    /**
     * Get message count.
     */
    public function getMessageCountAttribute(): int
    {
        return count($this->messages ?? []);
    }
}
