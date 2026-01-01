<?php

namespace App\Listeners;

use App\Events\ServerStatusChanged;
use App\Models\Server;
use App\Notifications\ServerOnline;

class ServerStatusListener
{
    /**
     * Handle the event.
     */
    public function handle(ServerStatusChanged $event): void
    {
        $server = $event->server;
        $previousStatus = $event->previousStatus;
        $currentStatus = $server->status;

        // Check if server came back online (was offline, now active)
        if ($previousStatus === Server::STATUS_OFFLINE && $currentStatus === Server::STATUS_ACTIVE) {
            $this->handleServerBackOnline($server);
        }
    }

    /**
     * Handle when a server comes back online after being offline.
     */
    protected function handleServerBackOnline(Server $server): void
    {
        $owner = $server->team?->owner;
        if (!$owner) {
            return;
        }

        // Calculate approximate downtime
        // We don't have exact offline timestamp, but we can estimate from last_heartbeat_at
        $downtime = 'unknown';
        if ($server->last_heartbeat_at) {
            // The heartbeat just happened, so downtime is roughly from when it was last seen
            // until now. But since we just received a heartbeat, we need to look at the
            // time gap before this heartbeat.
            $downtime = 'recently recovered';
        }

        $owner->notify(new ServerOnline($server, $downtime));
    }
}
