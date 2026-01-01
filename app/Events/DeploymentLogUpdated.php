<?php

namespace App\Events;

use App\Models\Deployment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentLogUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Deployment $deployment,
        public string $logLine
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('team.'.$this->deployment->webApp->server->team_id),
            new PrivateChannel('deployment.'.$this->deployment->id),
            new PrivateChannel('webapp.'.$this->deployment->web_app_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'deployment.log';
    }

    public function broadcastWith(): array
    {
        return [
            'deployment_id' => $this->deployment->id,
            'web_app_id' => $this->deployment->web_app_id,
            'log_line' => $this->logLine,
            'status' => $this->deployment->status,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
