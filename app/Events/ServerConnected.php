<?php

namespace App\Events;

use App\Models\Server;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerConnected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Server $server
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
        return 'server.connected';
    }

    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->server->id,
            'name' => $this->server->name,
            'ip_address' => $this->server->ip_address,
            'status' => $this->server->status,
            'connected_at' => now()->toIso8601String(),
        ];
    }
}
