<?php

namespace App\Filament\Widgets;

use App\Models\CronJob;
use App\Models\Database;
use App\Models\Deployment;
use App\Models\HealthMonitor;
use App\Models\Server;
use App\Models\Service;
use App\Models\WebApp;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $teamId = Filament::getTenant()?->id;

        if (!$teamId) {
            return [];
        }

        $serverCount = Server::where('team_id', $teamId)->count();
        $activeServers = Server::where('team_id', $teamId)->where('status', 'active')->count();
        $webAppCount = WebApp::whereHas('server', fn ($q) => $q->where('team_id', $teamId))->count();
        $databaseCount = Database::whereHas('server', fn ($q) => $q->where('team_id', $teamId))->count();
        $serviceCount = Service::whereHas('server', fn ($q) => $q->where('team_id', $teamId))
            ->where('status', 'active')
            ->count();
        $cronCount = CronJob::whereHas('server', fn ($q) => $q->where('team_id', $teamId))
            ->where('is_active', true)
            ->count();

        $recentDeployments = Deployment::whereHas('webApp.server', fn ($q) => $q->where('team_id', $teamId))
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $monitorsDown = HealthMonitor::where('team_id', $teamId)
            ->where('status', 'down')
            ->count();

        return [
            Stat::make('Servers', $serverCount)
                ->description("{$activeServers} active")
                ->descriptionIcon('heroicon-m-server')
                ->color($activeServers === $serverCount ? 'success' : 'warning'),

            Stat::make('Web Apps', $webAppCount)
                ->description('Deployed sites')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('primary'),

            Stat::make('Databases', $databaseCount)
                ->description('MySQL, PostgreSQL')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('info'),

            Stat::make('Services', $serviceCount)
                ->description('PHP, Node, Redis...')
                ->descriptionIcon('heroicon-m-cube')
                ->color('success'),

            Stat::make('Cron Jobs', $cronCount)
                ->description('Active schedules')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),

            Stat::make('Deployments', $recentDeployments)
                ->description('Last 7 days')
                ->descriptionIcon('heroicon-m-rocket-launch')
                ->color('success'),

            Stat::make('Monitors Down', $monitorsDown)
                ->description($monitorsDown > 0 ? 'Requires attention' : 'All systems operational')
                ->descriptionIcon($monitorsDown > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($monitorsDown > 0 ? 'danger' : 'success'),
        ];
    }
}
