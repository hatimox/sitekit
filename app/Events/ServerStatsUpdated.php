<?php

namespace App\Events;

use App\Models\Server;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerStatsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Server $server,
        public array $stats
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('team.'.$this->server->team_id),
            new PrivateChannel('server.'.$this->server->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'server.stats';
    }

    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->server->id,
            'cpu_percent' => $this->stats['cpu_percent'] ?? null,
            'memory_percent' => $this->stats['memory_percent'] ?? null,
            'disk_percent' => $this->stats['disk_percent'] ?? null,
            'load_1m' => $this->stats['load_1m'] ?? null,
            'load_5m' => $this->stats['load_5m'] ?? null,
            'load_15m' => $this->stats['load_15m'] ?? null,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
