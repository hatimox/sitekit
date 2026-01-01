<?php

namespace App\Http\Controllers;

use App\Models\HealthMonitor;
use App\Notifications\MonitorRecovered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class HeartbeatController extends Controller
{
    public function ping(string $token): JsonResponse
    {
        $monitor = HealthMonitor::where('heartbeat_token', $token)
            ->where('type', HealthMonitor::TYPE_HEARTBEAT)
            ->first();

        if (!$monitor) {
            return response()->json(['error' => 'Monitor not found'], 404);
        }

        if (!$monitor->is_active) {
            return response()->json(['error' => 'Monitor is paused'], 400);
        }

        $wasDown = $monitor->status === HealthMonitor::STATUS_DOWN;

        $monitor->update([
            'status' => HealthMonitor::STATUS_UP,
            'is_up' => true,
            'last_check_at' => now(),
            'last_up_at' => now(),
            'consecutive_failures' => 0,
            'consecutive_successes' => $monitor->consecutive_successes + 1,
        ]);

        if ($wasDown) {
            // Calculate downtime for notification
            $downtime = $monitor->last_down_at
                ? now()->diffForHumans($monitor->last_down_at, true)
                : null;

            // Send recovery notification
            try {
                $owner = $monitor->team->owner;
                if ($owner) {
                    $owner->notify(new MonitorRecovered($monitor, $downtime));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send heartbeat recovery notification', [
                    'monitor_id' => $monitor->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'monitor' => $monitor->name,
        ]);
    }
}
