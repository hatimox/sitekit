<?php

namespace App\Filament\Resources\ServerResource\Widgets;

use App\Models\Server;
use App\Models\ServerStat;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ServerMetricsChart extends ChartWidget
{
    protected static ?string $heading = 'Server Metrics';

    protected static ?string $pollingInterval = '30s';

    public ?Server $record = null;

    public string $metric = 'cpu';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        if (!$this->record) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $stats = $this->record->stats()
            ->where('recorded_at', '>=', now()->subHours(24))
            ->orderBy('recorded_at')
            ->get();

        if ($stats->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $labels = $stats->map(fn ($stat) => $stat->recorded_at->format('H:i'))->toArray();

        $datasets = match ($this->metric) {
            'cpu' => [
                [
                    'label' => 'CPU %',
                    'data' => $stats->pluck('cpu_percent')->toArray(),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'memory' => [
                [
                    'label' => 'Memory %',
                    'data' => $stats->pluck('memory_percent')->toArray(),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'disk' => [
                [
                    'label' => 'Disk %',
                    'data' => $stats->pluck('disk_percent')->toArray(),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'load' => [
                [
                    'label' => 'Load 1m',
                    'data' => $stats->pluck('load_1m')->toArray(),
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Load 5m',
                    'data' => $stats->pluck('load_5m')->toArray(),
                    'borderColor' => '#F97316',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.1)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Load 15m',
                    'data' => $stats->pluck('load_15m')->toArray(),
                    'borderColor' => '#8B5CF6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'tension' => 0.3,
                ],
            ],
            default => [
                [
                    'label' => 'CPU %',
                    'data' => $stats->pluck('cpu_percent')->toArray(),
                    'borderColor' => '#3B82F6',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Memory %',
                    'data' => $stats->pluck('memory_percent')->toArray(),
                    'borderColor' => '#10B981',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Disk %',
                    'data' => $stats->pluck('disk_percent')->toArray(),
                    'borderColor' => '#F59E0B',
                    'tension' => 0.3,
                ],
            ],
        };

        return [
            'datasets' => $datasets,
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
                    'max' => $this->metric === 'load' ? null : 100,
                    'ticks' => [
                        'callback' => $this->metric === 'load' ? null : "function(value) { return value + '%'; }",
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => $this->metric === 'load'
                            ? null
                            : "function(context) { return context.dataset.label + ': ' + context.parsed.y.toFixed(1) + '%'; }",
                    ],
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ];
    }

    public function getHeading(): ?string
    {
        return match ($this->metric) {
            'cpu' => 'CPU Usage (24h)',
            'memory' => 'Memory Usage (24h)',
            'disk' => 'Disk Usage (24h)',
            'load' => 'Load Average (24h)',
            default => 'Server Metrics (24h)',
        };
    }
}
