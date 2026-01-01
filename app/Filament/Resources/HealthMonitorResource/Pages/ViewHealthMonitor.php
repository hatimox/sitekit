<?php

namespace App\Filament\Resources\HealthMonitorResource\Pages;

use App\Filament\Resources\HealthMonitorResource;
use App\Filament\Widgets\HealthMonitorResponseTimeChartWidget;
use App\Filament\Widgets\HealthMonitorStatusTimelineWidget;
use App\Filament\Widgets\HealthMonitorUptimeChartWidget;
use App\Models\HealthMonitor;
use App\Services\UptimeMonitor;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewHealthMonitor extends ViewRecord
{
    protected static string $resource = HealthMonitorResource::class;

    public function getPollingInterval(): ?string
    {
        return '30s';
    }

    protected function getFooterWidgets(): array
    {
        return [
            HealthMonitorStatusTimelineWidget::make(['monitorId' => $this->record->id]),
            HealthMonitorResponseTimeChartWidget::make(['monitorId' => $this->record->id]),
            HealthMonitorUptimeChartWidget::make(['monitorId' => $this->record->id]),
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('check_now')
                ->label('Check Now')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function (HealthMonitor $record) {
                    $uptimeMonitor = app(UptimeMonitor::class);
                    $result = $uptimeMonitor->check($record);

                    if ($result->success) {
                        $record->markUp();
                        Notification::make()
                            ->title('Monitor is UP')
                            ->body("Response time: {$result->responseTime}ms")
                            ->success()
                            ->send();
                    } else {
                        $record->markDown($result->error);
                        Notification::make()
                            ->title('Monitor is DOWN')
                            ->body($result->error)
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('toggle')
                ->label(fn (HealthMonitor $record) => $record->is_active ? 'Pause' : 'Resume')
                ->icon(fn (HealthMonitor $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn (HealthMonitor $record) => $record->is_active ? 'warning' : 'success')
                ->action(function (HealthMonitor $record) {
                    $record->update([
                        'is_active' => !$record->is_active,
                        'status' => $record->is_active ? HealthMonitor::STATUS_PAUSED : HealthMonitor::STATUS_PENDING,
                    ]);

                    Notification::make()
                        ->title($record->is_active ? 'Monitor Resumed' : 'Monitor Paused')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('refresh_stats')
                ->label('Refresh Stats')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->action(function (HealthMonitor $record) {
                    $record->updateUptimeStats();

                    Notification::make()
                        ->title('Stats Refreshed')
                        ->body("24h uptime: {$record->uptime_24h}%")
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Current Status')
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                HealthMonitor::STATUS_UP => 'success',
                                HealthMonitor::STATUS_DOWN => 'danger',
                                HealthMonitor::STATUS_PENDING => 'warning',
                                HealthMonitor::STATUS_PAUSED => 'gray',
                                default => 'gray',
                            }),
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        TextEntry::make('last_check_at')
                            ->label('Last Check')
                            ->dateTime()
                            ->since(),
                        TextEntry::make('last_response_time')
                            ->label('Response Time')
                            ->suffix(' ms')
                            ->placeholder('-'),
                        TextEntry::make('consecutive_failures')
                            ->label('Consecutive Failures')
                            ->badge()
                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                        TextEntry::make('uptime_24h')
                            ->label('Uptime (24h)')
                            ->suffix('%')
                            ->placeholder('-')
                            ->color(fn (?float $state): string => match (true) {
                                $state === null => 'gray',
                                $state >= 99 => 'success',
                                $state >= 90 => 'warning',
                                default => 'danger',
                            }),
                    ])
                    ->columns(3),

                Section::make('Monitor Configuration')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn (string $state) => match ($state) {
                                HealthMonitor::TYPE_HTTP => 'HTTP',
                                HealthMonitor::TYPE_HTTPS => 'HTTPS',
                                HealthMonitor::TYPE_TCP => 'TCP',
                                HealthMonitor::TYPE_PING => 'Ping',
                                HealthMonitor::TYPE_SSL_EXPIRY => 'SSL Expiry',
                                HealthMonitor::TYPE_HEARTBEAT => 'Heartbeat',
                                default => $state,
                            }),
                        TextEntry::make('check_target')
                            ->label('Target')
                            ->copyable(),
                        TextEntry::make('interval_seconds')
                            ->label('Check Interval')
                            ->formatStateUsing(fn (int $state) => match (true) {
                                $state >= 3600 => ($state / 3600) . ' hour(s)',
                                $state >= 60 => ($state / 60) . ' minutes',
                                default => $state . ' seconds',
                            }),
                        TextEntry::make('timeout_seconds')
                            ->label('Timeout')
                            ->suffix(' seconds'),
                    ])
                    ->columns(3),

                Section::make('Thresholds')
                    ->schema([
                        TextEntry::make('failure_threshold')
                            ->label('Failure Threshold')
                            ->helperText('Consecutive failures before marking down'),
                        TextEntry::make('recovery_threshold')
                            ->label('Recovery Threshold')
                            ->helperText('Consecutive successes before marking up'),
                    ])
                    ->columns(2),

                Section::make('Heartbeat URL')
                    ->schema([
                        TextEntry::make('heartbeat_url')
                            ->label('Ping URL')
                            ->copyable()
                            ->helperText('Your cron job should ping this URL on each successful run'),
                    ])
                    ->visible(fn (HealthMonitor $record) => $record->type === HealthMonitor::TYPE_HEARTBEAT),

                Section::make('Last Error')
                    ->schema([
                        TextEntry::make('last_error')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (HealthMonitor $record) => $record->last_error !== null)
                    ->collapsed(),

                Section::make('Associations')
                    ->schema([
                        TextEntry::make('server.name')
                            ->label('Server')
                            ->placeholder('Not associated'),
                        TextEntry::make('webApp.name')
                            ->label('Web App')
                            ->placeholder('Not associated'),
                    ])
                    ->columns(2),

                Section::make('Timeline')
                    ->schema([
                        TextEntry::make('last_up_at')
                            ->label('Last Up')
                            ->dateTime()
                            ->placeholder('Never'),
                        TextEntry::make('last_down_at')
                            ->label('Last Down')
                            ->dateTime()
                            ->placeholder('Never'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                    ])
                    ->columns(3),
            ]);
    }
}
