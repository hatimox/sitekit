<?php

namespace App\Filament\Resources\SupervisorProgramResource\Pages;

use App\Filament\Resources\SupervisorProgramResource;
use App\Models\SupervisorProgram;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSupervisorProgram extends ViewRecord
{
    protected static string $resource = SupervisorProgramResource::class;

    public function getPollingInterval(): ?string
    {
        if ($this->record && $this->record->status === SupervisorProgram::STATUS_PENDING) {
            return '3s';
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('start')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (SupervisorProgram $record) => $record->isStopped())
                ->requiresConfirmation()
                ->action(function (SupervisorProgram $record) {
                    $record->dispatchJob('supervisor_start', [
                        'program_id' => $record->id,
                        'name' => $record->name,
                    ]);
                    $record->update(['status' => SupervisorProgram::STATUS_PENDING]);

                    Notification::make()
                        ->title('Starting Worker')
                        ->body("Starting {$record->name}...")
                        ->info()
                        ->send();
                }),

            Actions\Action::make('stop')
                ->icon('heroicon-o-stop')
                ->color('danger')
                ->visible(fn (SupervisorProgram $record) => $record->isActive())
                ->requiresConfirmation()
                ->action(function (SupervisorProgram $record) {
                    $record->dispatchJob('supervisor_stop', [
                        'program_id' => $record->id,
                        'name' => $record->name,
                    ]);
                    $record->update(['status' => SupervisorProgram::STATUS_STOPPED]);

                    Notification::make()
                        ->title('Stopping Worker')
                        ->body("Stopping {$record->name}...")
                        ->info()
                        ->send();
                }),

            Actions\Action::make('restart')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (SupervisorProgram $record) => $record->isActive())
                ->requiresConfirmation()
                ->action(function (SupervisorProgram $record) {
                    $record->dispatchJob('supervisor_restart', [
                        'program_id' => $record->id,
                        'name' => $record->name,
                    ]);

                    Notification::make()
                        ->title('Restarting Worker')
                        ->body("Restarting {$record->name}...")
                        ->info()
                        ->send();
                }),

            Actions\DeleteAction::make()
                ->before(function (SupervisorProgram $record) {
                    $record->dispatchJob('supervisor_delete', [
                        'program_id' => $record->id,
                        'name' => $record->name,
                    ]);
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Program Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                SupervisorProgram::STATUS_PENDING => 'warning',
                                SupervisorProgram::STATUS_ACTIVE => 'success',
                                SupervisorProgram::STATUS_STOPPED => 'gray',
                                SupervisorProgram::STATUS_FAILED => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('server.name')
                            ->label('Server'),
                        TextEntry::make('webApp.name')
                            ->label('Web App')
                            ->placeholder('Not linked'),
                    ])
                    ->columns(4),

                Section::make('Command')
                    ->schema([
                        TextEntry::make('command')
                            ->columnSpanFull()
                            ->fontFamily('mono'),
                        TextEntry::make('directory')
                            ->placeholder('Not set'),
                        TextEntry::make('user'),
                    ])
                    ->columns(2),

                Section::make('Process Settings')
                    ->schema([
                        TextEntry::make('numprocs')
                            ->label('Number of Processes'),
                        TextEntry::make('autostart')
                            ->badge()
                            ->color(fn (bool $state) => $state ? 'success' : 'gray')
                            ->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No'),
                        TextEntry::make('autorestart')
                            ->badge()
                            ->color(fn (bool $state) => $state ? 'success' : 'gray')
                            ->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No'),
                        TextEntry::make('startsecs')
                            ->label('Start Seconds'),
                        TextEntry::make('stopwaitsecs')
                            ->label('Stop Wait Seconds'),
                    ])
                    ->columns(5),

                Section::make('Metrics')
                    ->description('Updated from agent heartbeat')
                    ->schema([
                        TextEntry::make('cpu_percent')
                            ->label('CPU')
                            ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 1) . '%' : '-')
                            ->color(fn ($state) => match (true) {
                                $state === null => 'gray',
                                $state > 80 => 'danger',
                                $state > 50 => 'warning',
                                default => 'success',
                            }),
                        TextEntry::make('memory_mb')
                            ->label('Memory')
                            ->formatStateUsing(fn ($state) => $state !== null ? number_format($state) . ' MB' : '-'),
                        TextEntry::make('uptime_formatted')
                            ->label('Uptime')
                            ->placeholder('-'),
                        TextEntry::make('metrics_updated_at')
                            ->label('Last Updated')
                            ->dateTime()
                            ->placeholder('Never'),
                    ])
                    ->columns(4)
                    ->visible(fn (SupervisorProgram $record) => $record->isActive()),

                Section::make('Generated Configuration')
                    ->schema([
                        TextEntry::make('config')
                            ->label('')
                            ->getStateUsing(fn (SupervisorProgram $record) => $record->generateConfig())
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Error')
                    ->schema([
                        TextEntry::make('error_message')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (SupervisorProgram $record) => $record->error_message !== null),
            ]);
    }
}
