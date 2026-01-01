<?php

namespace App\Events;

use App\Models\AgentJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentJobUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AgentJob $job
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('team.'.$this->job->server->team_id),
            new PrivateChannel('server.'.$this->job->server_id),
        ];

        // Add webapp channel if job payload contains web_app_id
        $webAppId = $this->job->payload['web_app_id'] ?? null;
        if ($webAppId) {
            $channels[] = new PrivateChannel('webapp.'.$webAppId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'job.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->job->id,
            'server_id' => $this->job->server_id,
            'web_app_id' => $this->job->payload['web_app_id'] ?? null,
            'type' => $this->job->type,
            'status' => $this->job->status,
            'output' => $this->job->output,
            'error' => $this->job->error,
            'exit_code' => $this->job->exit_code,
            'started_at' => $this->job->started_at?->toIso8601String(),
            'completed_at' => $this->job->completed_at?->toIso8601String(),
        ];
    }
}
