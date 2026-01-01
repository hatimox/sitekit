<?php

namespace App\Filament\Resources\ServerResource\RelationManagers;

use App\Filament\Resources\ServiceResource;
use App\Models\Service;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $title = 'Services';

    protected static ?string $icon = 'heroicon-o-cog-6-tooth';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Service')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version')
                    ->badge()
                    ->color('info'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => Service::STATUS_PENDING,
                        'info' => Service::STATUS_INSTALLING,
                        'success' => Service::STATUS_ACTIVE,
                        'danger' => Service::STATUS_FAILED,
                        'gray' => Service::STATUS_REMOVING,
                    ]),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Service::STATUS_ACTIVE => 'Active',
                        Service::STATUS_STOPPED => 'Stopped',
                        Service::STATUS_PENDING => 'Pending',
                        Service::STATUS_FAILED => 'Failed',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label('Install Service')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => ServiceResource::getUrl('create')),
            ])
            ->actions([
                Tables\Actions\Action::make('restart')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Service $record) => $record->status === Service::STATUS_ACTIVE)
                    ->action(function (Service $record) {
                        $record->dispatchRestart();
                        Notification::make()
                            ->title('Service restart queued')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Service $record) => ServiceResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No services installed')
            ->emptyStateDescription('Install services like MySQL, Redis, or Nginx on this server.')
            ->emptyStateIcon('heroicon-o-cog-6-tooth');
    }
}
