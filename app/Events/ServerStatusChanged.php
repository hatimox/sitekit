<?php

namespace App\Events;

use App\Models\Server;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Server $server,
        public string $previousStatus
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
        return 'server.status';
    }

    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->server->id,
            'name' => $this->server->name,
            'previous_status' => $this->previousStatus,
            'current_status' => $this->server->status,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
