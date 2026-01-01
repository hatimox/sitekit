<?php

namespace App\Filament\Widgets;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ServersOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Servers';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Server::query()
                    ->where('team_id', Filament::getTenant()?->id)
                    ->orderBy('name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->url(fn (Server $record) => route('filament.app.resources.servers.edit', [
                        'tenant' => Filament::getTenant(),
                        'record' => $record,
                    ])),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => Server::STATUS_PENDING,
                        'info' => Server::STATUS_PROVISIONING,
                        'success' => Server::STATUS_ACTIVE,
                        'gray' => Server::STATUS_OFFLINE,
                        'danger' => Server::STATUS_FAILED,
                    ]),
                Tables\Columns\IconColumn::make('is_connected')
                    ->label('Connected')
                    ->boolean()
                    ->getStateUsing(fn (Server $record) => $record->isConnected()),
                Tables\Columns\TextColumn::make('webApps_count')
                    ->counts('webApps')
                    ->label('Apps'),
                Tables\Columns\TextColumn::make('services_count')
                    ->counts('services')
                    ->label('Services'),
                Tables\Columns\TextColumn::make('last_heartbeat_at')
                    ->label('Last Seen')
                    ->since()
                    ->placeholder('Never'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (Server $record) => route('filament.app.resources.servers.edit', [
                        'tenant' => Filament::getTenant(),
                        'record' => $record,
                    ]))
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated([5]);
    }
}
