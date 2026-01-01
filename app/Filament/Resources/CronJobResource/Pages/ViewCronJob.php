<?php

namespace App\Filament\Resources\CronJobResource\Pages;

use App\Filament\Resources\CronJobResource;
use App\Models\CronJob;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCronJob extends ViewRecord
{
    protected static string $resource = CronJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('toggle')
                ->label(fn (CronJob $record) => $record->is_active ? 'Disable' : 'Enable')
                ->icon(fn (CronJob $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn (CronJob $record) => $record->is_active ? 'warning' : 'success')
                ->action(function (CronJob $record) {
                    $record->update(['is_active' => !$record->is_active]);
                    $record->syncToServer();

                    Notification::make()
                        ->title($record->is_active ? 'Cron Job Enabled' : 'Cron Job Disabled')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('sync')
                ->label('Sync to Server')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function (CronJob $record) {
                    $record->syncToServer();

                    Notification::make()
                        ->title('Crontab Synced')
                        ->body('The crontab has been synchronized with the server.')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make()
                ->after(fn (CronJob $record) => $record->syncToServer()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Cron Job Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('server.name')
                            ->label('Server'),
                        TextEntry::make('webApp.name')
                            ->label('Web App')
                            ->placeholder('Not linked'),
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                    ])
                    ->columns(4),

                Section::make('Schedule Configuration')
                    ->schema([
                        TextEntry::make('command')
                            ->label('Command')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('schedule')
                            ->label('Cron Expression')
                            ->copyable(),
                        TextEntry::make('schedule_description')
                            ->label('Runs')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('user')
                            ->label('Run As')
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(3),

                Section::make('Full Crontab Entry')
                    ->schema([
                        TextEntry::make('crontab_line')
                            ->label('')
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
