<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Tool extends Model
{
    use HasFactory, HasUuids;

    // Tool Names
    public const NAME_NODE = 'node';
    public const NAME_NPM = 'npm';
    public const NAME_YARN = 'yarn';
    public const NAME_COMPOSER = 'composer';
    public const NAME_GIT = 'git';
    public const NAME_CERTBOT = 'certbot';
    public const NAME_WP_CLI = 'wp-cli';

    protected $fillable = [
        'server_id',
        'name',
        'version',
        'path',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get the team that owns this tool (through server).
     * Required for Filament tenant ownership.
     */
    public function team(): HasOneThrough
    {
        return $this->hasOneThrough(
            Team::class,
            Server::class,
            'id',           // Foreign key on servers table
            'id',           // Foreign key on teams table
            'server_id',    // Local key on tools table
            'team_id'       // Local key on servers table
        );
    }

    public function getDisplayNameAttribute(): string
    {
        $names = self::getToolDisplayNames();
        return $names[$this->name] ?? ucfirst($this->name);
    }

    public static function getToolDisplayNames(): array
    {
        return [
            self::NAME_NODE => 'Node.js',
            self::NAME_NPM => 'npm',
            self::NAME_YARN => 'Yarn',
            self::NAME_COMPOSER => 'Composer',
            self::NAME_GIT => 'Git',
            self::NAME_CERTBOT => 'Certbot',
            self::NAME_WP_CLI => 'WP-CLI',
        ];
    }

    public static function getToolIcons(): array
    {
        return [
            self::NAME_NODE => 'heroicon-o-code-bracket',
            self::NAME_NPM => 'heroicon-o-cube',
            self::NAME_YARN => 'heroicon-o-cube',
            self::NAME_COMPOSER => 'heroicon-o-musical-note',
            self::NAME_GIT => 'heroicon-o-arrow-path-rounded-square',
            self::NAME_CERTBOT => 'heroicon-o-shield-check',
            self::NAME_WP_CLI => 'heroicon-o-command-line',
        ];
    }

    public function getIconAttribute(): string
    {
        $icons = self::getToolIcons();
        return $icons[$this->name] ?? 'heroicon-o-wrench';
    }
}
