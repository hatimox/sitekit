<?php

namespace App\Filament\Widgets;

use App\Models\HealthMonitor;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class HealthMonitorStatusTimelineWidget extends Widget
{
    protected static string $view = 'filament.widgets.health-monitor-status-timeline';
    protected static ?string $pollingInterval = '60s';

    public ?string $monitorId = null;

    public function mount(?string $monitorId = null): void
    {
        $this->monitorId = $monitorId;
    }

    protected function getViewData(): array
    {
        $emptyData = [
            'slots' => [],
            'uptime_24h' => null,
            'uptime_7d' => null,
            'uptime_30d' => null,
            'avg_response_time' => null,
        ];

        if (!$this->monitorId) {
            return $emptyData;
        }

        $monitor = HealthMonitor::find($this->monitorId);
        if (!$monitor) {
            return $emptyData;
        }

        // Get hourly status for the last 24 hours (as slots for the timeline)
        $slots = [];
        for ($i = 23; $i >= 0; $i--) {
            $hourStart = now()->subHours($i)->startOfHour();
            $hourEnd = $hourStart->copy()->endOfHour();

            $checks = $monitor->logs()
                ->whereBetween('checked_at', [$hourStart, $hourEnd])
                ->get();

            $total = $checks->count();
            $upCount = $checks->where('status', 'up')->count();
            $downCount = $total - $upCount;

            $slots[] = [
                'hour' => $hourStart->format('H:i'),
                'date' => $hourStart->format('M d'),
                'total' => $total,
                'up' => $upCount,
                'down' => $downCount,
                'status' => $total === 0 ? 'no-data' : ($downCount > 0 ? ($upCount === 0 ? 'down' : 'degraded') : 'up'),
            ];
        }

        return [
            'slots' => $slots,
            'uptime_24h' => $monitor->uptime_24h,
            'uptime_7d' => $monitor->uptime_7d,
            'uptime_30d' => $monitor->uptime_30d,
            'avg_response_time' => $monitor->avg_response_time,
        ];
    }
}
