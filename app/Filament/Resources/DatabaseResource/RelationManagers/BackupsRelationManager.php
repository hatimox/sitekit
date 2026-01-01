<?php

namespace App\Filament\Resources\DatabaseResource\RelationManagers;

use App\Models\DatabaseBackup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class BackupsRelationManager extends RelationManager
{
    protected static string $relationship = 'backups';

    protected static ?string $recordTitleAttribute = 'filename';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('filename')
            ->columns([
                Tables\Columns\TextColumn::make('filename')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'running',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('size_formatted')
                    ->label('Size'),
                Tables\Columns\BadgeColumn::make('trigger')
                    ->colors([
                        'primary' => 'manual',
                        'secondary' => 'scheduled',
                    ]),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'running' => 'Running',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('trigger')
                    ->options([
                        'manual' => 'Manual',
                        'scheduled' => 'Scheduled',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_backup')
                    ->label('Create Backup')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Create Database Backup')
                    ->modalDescription('This will create a new backup of the database. The backup process will run in the background.')
                    ->action(function () {
                        $database = $this->getOwnerRecord();

                        if ($database->backups()->whereIn('status', ['pending', 'running'])->exists()) {
                            Notification::make()
                                ->title('Backup in Progress')
                                ->body('A backup is already in progress for this database.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $database->createBackup();

                        Notification::make()
                            ->title('Backup Started')
                            ->body('The database backup has been queued and will be processed shortly.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (DatabaseBackup $record): bool => $record->isCompleted() && $record->path)
                    ->requiresConfirmation()
                    ->modalHeading('Restore Database from Backup')
                    ->modalDescription(fn (DatabaseBackup $record) => "This will restore the database from backup '{$record->filename}'. All current data will be replaced. This action cannot be undone.")
                    ->modalSubmitActionLabel('Restore Database')
                    ->action(function (DatabaseBackup $record) {
                        $database = $this->getOwnerRecord();

                        $database->dispatchJob('import_database', [
                            'database_id' => $database->id,
                            'db_name' => $database->name,
                            'type' => $database->type,
                            'file_path' => $record->path,
                            'drop_existing' => true,
                        ]);

                        Notification::make()
                            ->title('Database Restore Started')
                            ->body("Restoring database from backup '{$record->filename}'...")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->visible(fn (DatabaseBackup $record): bool => $record->isCompleted() && $record->path)
                    ->url(fn (DatabaseBackup $record): string => route('backups.download', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('view_error')
                    ->icon('heroicon-o-exclamation-circle')
                    ->color('danger')
                    ->visible(fn (DatabaseBackup $record): bool => $record->isFailed() && $record->error_message)
                    ->modalHeading('Backup Error')
                    ->modalContent(fn (DatabaseBackup $record) => view('filament.modals.error-message', [
                        'message' => $record->error_message,
                    ])),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (DatabaseBackup $record): bool => $record->isCompleted() || $record->isFailed()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->poll('5s');
    }
}
