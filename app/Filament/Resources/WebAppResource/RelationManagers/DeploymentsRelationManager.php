<?php

namespace App\Filament\Resources\WebAppResource\RelationManagers;

use App\Models\Deployment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class DeploymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'deployments';

    protected static ?string $recordTitleAttribute = 'commit_hash';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('commit_hash')
            ->columns([
                Tables\Columns\TextColumn::make('commit_hash')
                    ->label('Commit')
                    ->limit(8)
                    ->copyable()
                    ->copyableState(fn ($state) => $state),
                Tables\Columns\TextColumn::make('commit_message')
                    ->label('Message')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),
                Tables\Columns\TextColumn::make('branch')
                    ->badge()
                    ->color('info'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => Deployment::STATUS_PENDING,
                        'info' => [Deployment::STATUS_CLONING, Deployment::STATUS_BUILDING, Deployment::STATUS_DEPLOYING],
                        'success' => Deployment::STATUS_ACTIVE,
                        'danger' => Deployment::STATUS_FAILED,
                        'gray' => Deployment::STATUS_ROLLED_BACK,
                    ]),
                Tables\Columns\TextColumn::make('triggered_by')
                    ->label('By')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(function (Deployment $record): ?string {
                        if (!$record->started_at) {
                            return null;
                        }
                        $end = $record->finished_at ?? now();
                        $seconds = $end->diffInSeconds($record->started_at);
                        if ($seconds < 60) {
                            return "{$seconds}s";
                        }
                        $minutes = floor($seconds / 60);
                        $remaining = $seconds % 60;
                        return "{$minutes}m {$remaining}s";
                    }),
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
                        'active' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('deploy')
                    ->label('Deploy Now')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Deploy Application')
                    ->modalDescription('This will trigger a new deployment. Are you sure you want to proceed?')
                    ->action(function () {
                        $webApp = $this->getOwnerRecord();

                        $hasActiveDeployment = $webApp->deployments()
                            ->whereIn('status', [Deployment::STATUS_PENDING, Deployment::STATUS_CLONING, Deployment::STATUS_BUILDING, Deployment::STATUS_DEPLOYING])
                            ->exists();

                        if ($hasActiveDeployment) {
                            Notification::make()
                                ->title('Deployment in Progress')
                                ->body('A deployment is already in progress.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $webApp->deploy('manual');

                        Notification::make()
                            ->title('Deployment Started')
                            ->body('The deployment has been queued and will start shortly.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_log')
                    ->label('View Log')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->modalHeading(fn (Deployment $record) => "Deployment Log - {$record->commit_hash}")
                    ->modalContent(fn (Deployment $record) => new HtmlString(
                        '<div class="bg-gray-900 text-gray-100 p-4 rounded-lg font-mono text-sm overflow-x-auto max-h-96 overflow-y-auto whitespace-pre-wrap">' .
                        nl2br(e($record->log ?: 'No log output yet...')) .
                        '</div>'
                    ))
                    ->modalWidth('4xl'),
                Tables\Actions\Action::make('rollback')
                    ->label('Rollback')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Deployment $record) => $record->status === Deployment::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->modalHeading('Rollback to this Deployment')
                    ->modalDescription('This will revert the application to this deployment version.')
                    ->action(function (Deployment $record) {
                        $webApp = $this->getOwnerRecord();

                        $webApp->rollbackTo($record);

                        Notification::make()
                            ->title('Rollback Initiated')
                            ->body("Rolling back to commit {$record->commit_hash}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Deployment $record) => in_array($record->status, [Deployment::STATUS_PENDING, Deployment::STATUS_CLONING, Deployment::STATUS_BUILDING, Deployment::STATUS_DEPLOYING]))
                    ->requiresConfirmation()
                    ->action(function (Deployment $record) {
                        $record->markAs(Deployment::STATUS_FAILED);

                        Notification::make()
                            ->title('Deployment Cancelled')
                            ->success()
                            ->send();
                    }),
            ])
            ->poll('5s');
    }
}
