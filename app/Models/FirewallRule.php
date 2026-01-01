<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FirewallRule extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    public const ACTION_ALLOW = 'allow';
    public const ACTION_DENY = 'deny';

    public const PROTOCOL_TCP = 'tcp';
    public const PROTOCOL_UDP = 'udp';
    public const PROTOCOL_ANY = 'any';

    protected $fillable = [
        'server_id',
        'team_id',
        'direction',
        'action',
        'protocol',
        'port',
        'from_ip',
        'description',
        'is_active',
        'is_system',
        'is_pending_confirmation',
        'confirmation_token',
        'confirmation_expires_at',
        'rollback_reason',
        'rolled_back_at',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'is_pending_confirmation' => 'boolean',
            'confirmation_expires_at' => 'datetime',
            'rolled_back_at' => 'datetime',
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

    public static function createDefaultRules(Server $server): void
    {
        // SSH - Always allow (system rule)
        self::create([
            'server_id' => $server->id,
            'team_id' => $server->team_id,
            'direction' => self::DIRECTION_IN,
            'action' => self::ACTION_ALLOW,
            'protocol' => self::PROTOCOL_TCP,
            'port' => '22',
            'from_ip' => 'any',
            'description' => 'SSH Access',
            'is_system' => true,
            'order' => 1,
        ]);

        // HTTP
        self::create([
            'server_id' => $server->id,
            'team_id' => $server->team_id,
            'direction' => self::DIRECTION_IN,
            'action' => self::ACTION_ALLOW,
            'protocol' => self::PROTOCOL_TCP,
            'port' => '80',
            'from_ip' => 'any',
            'description' => 'HTTP',
            'order' => 10,
        ]);

        // HTTPS
        self::create([
            'server_id' => $server->id,
            'team_id' => $server->team_id,
            'direction' => self::DIRECTION_IN,
            'action' => self::ACTION_ALLOW,
            'protocol' => self::PROTOCOL_TCP,
            'port' => '443',
            'from_ip' => 'any',
            'description' => 'HTTPS',
            'order' => 11,
        ]);
    }

    public function toUfwCommand(): string
    {
        $action = $this->action === self::ACTION_ALLOW ? 'allow' : 'deny';
        $direction = $this->direction === self::DIRECTION_IN ? 'in' : 'out';
        $proto = $this->protocol && $this->protocol !== self::PROTOCOL_ANY ? "proto {$this->protocol}" : '';
        $from = $this->from_ip && $this->from_ip !== 'any' ? "from {$this->from_ip}" : '';

        return trim("ufw {$action} {$direction} {$proto} to any port {$this->port} {$from}");
    }

    public function getUfwCommandAttribute(): string
    {
        return $this->toUfwCommand();
    }

    public function toUfwDeleteCommand(): string
    {
        return str_replace('ufw ', 'ufw delete ', $this->toUfwCommand());
    }

    public function confirm(): void
    {
        $this->update([
            'is_pending_confirmation' => false,
            'confirmation_token' => null,
        ]);
    }

    public function revert(): void
    {
        $this->update(['is_active' => false]);

        AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->team_id,
            'type' => 'revert_firewall_rule',
            'payload' => [
                'rule_id' => $this->id,
                'command' => $this->toUfwDeleteCommand(),
            ],
        ]);
    }

    public function dispatchApply(): AgentJob
    {
        return AgentJob::create([
            'server_id' => $this->server_id,
            'team_id' => $this->team_id,
            'type' => 'apply_firewall_rule',
            'payload' => [
                'rule_id' => $this->id,
                'command' => $this->toUfwCommand(),
            ],
        ]);
    }
}
