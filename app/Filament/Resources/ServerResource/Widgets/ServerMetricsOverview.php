<?php

namespace App\Filament\Resources\ServerResource\Widgets;

use App\Models\Server;
use Filament\Widgets\Widget;

class ServerMetricsOverview extends Widget
{
    protected static string $view = 'filament.resources.server-resource.widgets.server-metrics-overview';

    public ?Server $record = null;

    protected int|string|array $columnSpan = 'full';

    public string $activeTab = 'all';

    protected function getViewData(): array
    {
        if (!$this->record) {
            return [
                'hasStats' => false,
                'stats' => null,
                'latestStats' => null,
            ];
        }

        $latestStats = $this->record->latestStats();
        $stats = $this->record->stats()
            ->where('recorded_at', '>=', now()->subHours(24))
            ->orderBy('recorded_at')
            ->get();

        return [
            'hasStats' => $stats->isNotEmpty(),
            'stats' => $stats,
            'latestStats' => $latestStats,
            'server' => $this->record,
        ];
    }
}
