<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'team_id',
        'user_id',
        'conversation_id',
        'provider',
        'model',
        'endpoint',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_usd',
        'response_time_ms',
        'cached',
        'success',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cost_usd' => 'decimal:6',
            'response_time_ms' => 'integer',
            'cached' => 'boolean',
            'success' => 'boolean',
            'created_at' => 'datetime',
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

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class);
    }

    /**
     * Get usage statistics for a team.
     */
    public static function getTeamStats(string $teamId, ?string $period = 'day'): array
    {
        $query = self::where('team_id', $teamId);

        $query = match ($period) {
            'hour' => $query->where('created_at', '>=', now()->subHour()),
            'day' => $query->where('created_at', '>=', now()->subDay()),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            default => $query,
        };

        return [
            'total_requests' => $query->count(),
            'successful_requests' => (clone $query)->where('success', true)->count(),
            'total_tokens' => (clone $query)->sum('total_tokens'),
            'total_cost' => (clone $query)->sum('cost_usd'),
            'avg_response_time' => (clone $query)->avg('response_time_ms'),
            'by_provider' => (clone $query)->selectRaw('provider, COUNT(*) as count, SUM(total_tokens) as tokens')
                ->groupBy('provider')
                ->get()
                ->keyBy('provider')
                ->toArray(),
        ];
    }
}
