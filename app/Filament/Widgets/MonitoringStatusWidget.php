<?php

namespace App\Filament\Widgets;

use App\Models\HealthMonitor;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MonitoringStatusWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Health Monitors';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                HealthMonitor::query()
                    ->where('team_id', Filament::getTenant()?->id)
                    ->where('is_active', true)
                    ->orderByRaw("CASE WHEN status = 'down' THEN 0 WHEN status = 'pending' THEN 1 ELSE 2 END")
                    ->orderBy('name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->url(fn (HealthMonitor $record) => route('filament.app.resources.health-monitors.edit', [
                        'tenant' => Filament::getTenant(),
                        'record' => $record,
                    ])),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => HealthMonitor::TYPE_HTTP,
                        'info' => HealthMonitor::TYPE_TCP,
                        'warning' => HealthMonitor::TYPE_HEARTBEAT,
                    ])
                    ->formatStateUsing(fn (string $state) => strtoupper($state)),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => HealthMonitor::STATUS_UP,
                        'danger' => HealthMonitor::STATUS_DOWN,
                        'warning' => HealthMonitor::STATUS_PENDING,
                        'gray' => HealthMonitor::STATUS_PAUSED,
                    ]),
                Tables\Columns\TextColumn::make('check_target')
                    ->label('Target')
                    ->limit(40),
                Tables\Columns\TextColumn::make('last_check_at')
                    ->label('Last Check')
                    ->since()
                    ->placeholder('Never'),
                Tables\Columns\TextColumn::make('consecutive_failures')
                    ->label('Failures')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'success'),
            ])
            ->actions([
                Tables\Actions\Action::make('check')
                    ->label('Check Now')
                    ->icon('heroicon-m-arrow-path')
                    ->action(function (HealthMonitor $record) {
                        $service = app(\App\Services\UptimeMonitor::class);
                        $result = $service->check($record);

                        if ($result->success) {
                            $record->markUp();
                        } else {
                            $record->markDown($result->error);
                        }
                    }),
            ])
            ->paginated([5]);
    }
}
