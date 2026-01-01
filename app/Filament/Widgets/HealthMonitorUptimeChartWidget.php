<?php

namespace App\Filament\Widgets;

use App\Models\HealthMonitor;
use Filament\Widgets\ChartWidget;

class HealthMonitorUptimeChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Uptime History (24h)';
    protected static ?string $pollingInterval = '60s';
    protected static ?string $maxHeight = '200px';

    public ?string $monitorId = null;

    public function mount(?string $monitorId = null): void
    {
        $this->monitorId = $monitorId;
    }

    protected function getData(): array
    {
        if (!$this->monitorId) {
            return ['datasets' => [], 'labels' => []];
        }

        $monitor = HealthMonitor::find($this->monitorId);
        if (!$monitor) {
            return ['datasets' => [], 'labels' => []];
        }

        $data = $monitor->getHourlyUptime(24);

        $labels = array_column($data, 'hour');
        $uptimes = array_map(fn ($d) => $d['uptime'] ?? 0, $data);

        // Color based on uptime - green for 100%, yellow for 90-99%, red for <90%
        $colors = array_map(function ($uptime) {
            if ($uptime === null || $uptime === 0) {
                return 'rgba(156, 163, 175, 0.6)'; // Gray - no data
            }
            if ($uptime >= 99) {
                return 'rgba(34, 197, 94, 0.8)'; // Green
            }
            if ($uptime >= 90) {
                return 'rgba(234, 179, 8, 0.8)'; // Yellow
            }
            return 'rgba(239, 68, 68, 0.8)'; // Red
        }, $uptimes);

        return [
            'datasets' => [
                [
                    'label' => 'Uptime %',
                    'data' => $uptimes,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => 100,
                    'title' => [
                        'display' => true,
                        'text' => 'Uptime %',
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'maxTicksLimit' => 12,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) { return context.parsed.y + '%'; }",
                    ],
                ],
            ],
        ];
    }
}
