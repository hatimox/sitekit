<?php

namespace App\Filament\Widgets;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServersOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $team = Filament::getTenant();

        if (!$team) {
            return [];
        }

        $servers = $team->servers;
        $totalServers = $servers->count();

        if ($totalServers === 0) {
            return [
                Stat::make('Servers', 0)
                    ->description('No servers connected yet')
                    ->icon('heroicon-o-server')
                    ->color('gray'),
            ];
        }

        $activeServers = $servers->where('status', Server::STATUS_ACTIVE)->count();
        $offlineServers = $servers->where('status', Server::STATUS_OFFLINE)->count();

        // Calculate averages from active servers with stats
        $avgCpu = 0;
        $avgMemory = 0;
        $avgDisk = 0;
        $statsCount = 0;

        foreach ($servers->where('status', Server::STATUS_ACTIVE) as $server) {
            $stats = $server->latestStats();
            if ($stats) {
                $avgCpu += $stats->cpu_percent;
                $avgMemory += $stats->memory_percent;
                $avgDisk += $stats->disk_percent;
                $statsCount++;
            }
        }

        if ($statsCount > 0) {
            $avgCpu = round($avgCpu / $statsCount, 1);
            $avgMemory = round($avgMemory / $statsCount, 1);
            $avgDisk = round($avgDisk / $statsCount, 1);
        }

        return [
            Stat::make('Active Servers', $activeServers . ' / ' . $totalServers)
                ->description($offlineServers > 0 ? "{$offlineServers} offline" : 'All servers online')
                ->icon('heroicon-o-server-stack')
                ->color($offlineServers > 0 ? 'warning' : 'success'),

            Stat::make('Avg CPU', $statsCount > 0 ? "{$avgCpu}%" : 'N/A')
                ->description('Across active servers')
                ->icon('heroicon-o-cpu-chip')
                ->color($avgCpu > 90 ? 'danger' : ($avgCpu > 70 ? 'warning' : 'success'))
                ->chart($this->getCpuTrend($team)),

            Stat::make('Avg Memory', $statsCount > 0 ? "{$avgMemory}%" : 'N/A')
                ->description('Across active servers')
                ->icon('heroicon-o-circle-stack')
                ->color($avgMemory > 90 ? 'danger' : ($avgMemory > 70 ? 'warning' : 'success'))
                ->chart($this->getMemoryTrend($team)),

            Stat::make('Avg Disk', $statsCount > 0 ? "{$avgDisk}%" : 'N/A')
                ->description('Across active servers')
                ->icon('heroicon-o-server')
                ->color($avgDisk > 90 ? 'danger' : ($avgDisk > 80 ? 'warning' : 'success')),
        ];
    }

    protected function getCpuTrend($team): array
    {
        // Get hourly averages for the last 12 hours
        $trend = [];
        for ($i = 11; $i >= 0; $i--) {
            $hour = now()->subHours($i);
            $avg = \App\Models\ServerStat::whereHas('server', fn ($q) => $q->where('team_id', $team->id))
                ->where('recorded_at', '>=', $hour->copy()->startOfHour())
                ->where('recorded_at', '<', $hour->copy()->endOfHour())
                ->avg('cpu_percent');
            $trend[] = round($avg ?? 0, 1);
        }
        return $trend;
    }

    protected function getMemoryTrend($team): array
    {
        $trend = [];
        for ($i = 11; $i >= 0; $i--) {
            $hour = now()->subHours($i);
            $avg = \App\Models\ServerStat::whereHas('server', fn ($q) => $q->where('team_id', $team->id))
                ->where('recorded_at', '>=', $hour->copy()->startOfHour())
                ->where('recorded_at', '<', $hour->copy()->endOfHour())
                ->avg('memory_percent');
            $trend[] = round($avg ?? 0, 1);
        }
        return $trend;
    }
}
