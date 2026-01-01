<?php

namespace App\Filament\Widgets;

use App\Models\HealthMonitor;
use Filament\Widgets\ChartWidget;

class HealthMonitorResponseTimeChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Response Time (24h)';
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

        $data = $monitor->getResponseTimeChartData(24);

        $labels = array_column($data, 'time');
        $values = array_column($data, 'value');

        return [
            'datasets' => [
                [
                    'label' => 'Response Time (ms)',
                    'data' => $values,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => count($values) > 50 ? 0 : 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'ms',
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
                        'label' => "function(context) { return context.parsed.y + ' ms'; }",
                    ],
                ],
            ],
        ];
    }
}
