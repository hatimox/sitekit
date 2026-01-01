<?php

namespace App\Listeners;

use App\Events\ServerStatsUpdated;
use App\Models\Server;
use App\Notifications\ServerHighDisk;
use App\Notifications\ServerHighLoad;
use App\Notifications\ServerHighMemory;
use App\Notifications\ServerResourcesNormal;

class ServerStatsListener
{
    /**
     * Handle the event.
     */
    public function handle(ServerStatsUpdated $event): void
    {
        $server = $event->server;
        $stats = $event->stats;

        // Skip if resource alerts are disabled
        if (!$server->resource_alerts_enabled) {
            return;
        }

        // Get current metrics
        $load = $stats['load_1m'] ?? 0;
        $memory = $stats['memory_percent'] ?? 0;
        $disk = $stats['disk_percent'] ?? 0;

        // Get thresholds
        $loadThreshold = $server->getEffectiveLoadThreshold();
        $memoryThreshold = $server->alert_memory_threshold ?? 90;
        $diskThreshold = $server->alert_disk_threshold ?? 90;

        // Check each metric
        $loadExceeded = $load >= $loadThreshold;
        $memoryExceeded = $memory >= $memoryThreshold;
        $diskExceeded = $disk >= $diskThreshold;

        // Track if we need to update the server
        $updates = [];
        $owner = $server->team?->owner;

        // Handle Load Alert
        if ($loadExceeded && !$server->is_load_alert_active) {
            $updates['is_load_alert_active'] = true;
            if ($owner && $server->canSendResourceAlert()) {
                $owner->notify(new ServerHighLoad($server, $load, $loadThreshold));
                $updates['last_resource_alert_at'] = now();
            }
        } elseif (!$loadExceeded && $server->is_load_alert_active) {
            $updates['is_load_alert_active'] = false;
        }

        // Handle Memory Alert
        if ($memoryExceeded && !$server->is_memory_alert_active) {
            $updates['is_memory_alert_active'] = true;
            if ($owner && $server->canSendResourceAlert()) {
                $owner->notify(new ServerHighMemory($server, $memory, $memoryThreshold));
                $updates['last_resource_alert_at'] = now();
            }
        } elseif (!$memoryExceeded && $server->is_memory_alert_active) {
            $updates['is_memory_alert_active'] = false;
        }

        // Handle Disk Alert
        if ($diskExceeded && !$server->is_disk_alert_active) {
            $updates['is_disk_alert_active'] = true;
            if ($owner && $server->canSendResourceAlert()) {
                $owner->notify(new ServerHighDisk($server, $disk, $diskThreshold));
                $updates['last_resource_alert_at'] = now();
            }
        } elseif (!$diskExceeded && $server->is_disk_alert_active) {
            $updates['is_disk_alert_active'] = false;
        }

        // Apply updates if any
        if (!empty($updates)) {
            $server->update($updates);
            $server->refresh();
        }

        // Check if ALL alerts are now resolved - send "back to normal"
        $wasAlerting = $server->is_load_alert_active || $server->is_memory_alert_active || $server->is_disk_alert_active;
        $nowNormal = !$loadExceeded && !$memoryExceeded && !$diskExceeded;

        // Only send "resources normal" if we had active alerts and now all are resolved
        if (!$nowNormal) {
            return;
        }

        // Check if any alerts were just deactivated (meaning we were alerting before)
        $justResolved = isset($updates['is_load_alert_active']) && !$updates['is_load_alert_active']
            || isset($updates['is_memory_alert_active']) && !$updates['is_memory_alert_active']
            || isset($updates['is_disk_alert_active']) && !$updates['is_disk_alert_active'];

        if ($justResolved && $owner) {
            $owner->notify(new ServerResourcesNormal($server, [
                'load' => $load,
                'memory' => $memory,
                'disk' => $disk,
            ]));
        }
    }
}
