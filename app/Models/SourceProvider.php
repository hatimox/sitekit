<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceProvider extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    public const PROVIDER_GITHUB = 'github';
    public const PROVIDER_GITLAB = 'gitlab';
    public const PROVIDER_BITBUCKET = 'bitbucket';

    protected $fillable = [
        'team_id',
        'provider',
        'name',
        'provider_user_id',
        'provider_username',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function webApps(): HasMany
    {
        return $this->hasMany(WebApp::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function isTokenExpired(): bool
    {
        if ($this->token_expires_at === null) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    public function getProviderIconAttribute(): string
    {
        return match ($this->provider) {
            self::PROVIDER_GITHUB => 'github',
            self::PROVIDER_GITLAB => 'gitlab',
            self::PROVIDER_BITBUCKET => 'bitbucket',
            default => 'git',
        };
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->provider_username})";
    }
}
