<?php

namespace App\Filament\Widgets;

use App\Models\ServiceStat;
use Filament\Widgets\ChartWidget;

class ServiceCpuChartWidget extends ChartWidget
{
    protected static ?string $heading = 'CPU Usage (24h)';
    protected static ?string $pollingInterval = '30s';
    protected static ?string $maxHeight = '200px';

    public ?string $serviceId = null;

    protected function getData(): array
    {
        if (!$this->serviceId) {
            return ['datasets' => [], 'labels' => []];
        }

        $metrics = ServiceStat::getMetricsForChart($this->serviceId, '24h');

        return [
            'datasets' => [
                [
                    'label' => 'CPU %',
                    'data' => $metrics['cpu'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $metrics['labels'],
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
                    'max' => 100,
                    'title' => [
                        'display' => true,
                        'text' => 'CPU %',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }
}
