<?php

namespace App\Filament\Resources\WebAppResource\RelationManagers;

use App\Filament\Resources\CronJobResource;
use App\Models\CronJob;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CronJobsRelationManager extends RelationManager
{
    protected static string $relationship = 'cronJobs';

    protected static ?string $title = 'Cron Jobs';

    protected static ?string $icon = 'heroicon-o-clock';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('command')
                    ->limit(40)
                    ->tooltip(fn (CronJob $record) => $record->command),
                Tables\Columns\TextColumn::make('schedule_description')
                    ->label('Schedule'),
                Tables\Columns\TextColumn::make('user')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label('Add Cron Job')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => CronJobResource::getUrl('create')),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->icon(fn (CronJob $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (CronJob $record) => $record->is_active ? 'warning' : 'success')
                    ->action(function (CronJob $record) {
                        $record->update(['is_active' => !$record->is_active]);
                        $record->syncToServer();
                    }),
                Tables\Actions\Action::make('edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (CronJob $record) => CronJobResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('No cron jobs')
            ->emptyStateDescription('Schedule recurring tasks for this application.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
