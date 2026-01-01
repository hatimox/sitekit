<?php

namespace App\Filament\Widgets;

use App\Models\ServiceStat;
use Filament\Widgets\ChartWidget;

class ServiceMemoryChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Memory Usage (24h)';
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
                    'label' => 'Memory (MB)',
                    'data' => $metrics['memory'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
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
                    'title' => [
                        'display' => true,
                        'text' => 'Memory (MB)',
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
