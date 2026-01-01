<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentJobResource\Pages;
use App\Models\AgentJob;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AgentJobResource extends Resource
{
    protected static ?string $model = AgentJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Agent Jobs';

    protected static ?string $modelLabel = 'Job';

    protected static ?string $pluralModelLabel = 'Jobs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Job Details')
                    ->schema([
                        Forms\Components\TextInput::make('type')
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                AgentJob::STATUS_PENDING => 'Pending',
                                AgentJob::STATUS_QUEUED => 'Queued',
                                AgentJob::STATUS_RUNNING => 'Running',
                                AgentJob::STATUS_COMPLETED => 'Completed',
                                AgentJob::STATUS_FAILED => 'Failed',
                                AgentJob::STATUS_CANCELLED => 'Cancelled',
                            ])
                            ->disabled(),
                        Forms\Components\TextInput::make('priority')
                            ->disabled(),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Payload')
                    ->schema([
                        Forms\Components\KeyValue::make('payload')
                            ->disabled(),
                    ]),
                Forms\Components\Section::make('Output')
                    ->schema([
                        Forms\Components\Textarea::make('output')
                            ->disabled()
                            ->rows(10)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('error')
                            ->disabled()
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(fn ($record) => empty($record?->output) && empty($record?->error)),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Job Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'gray',
                                'queued' => 'info',
                                'running' => 'warning',
                                'completed' => 'success',
                                'failed' => 'danger',
                                'cancelled' => 'gray',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('priority')
                            ->badge()
                            ->color('gray'),
                        Infolists\Components\TextEntry::make('server.name')
                            ->label('Server'),
                        Infolists\Components\TextEntry::make('exit_code')
                            ->badge()
                            ->color(fn ($state): string => $state === 0 ? 'success' : 'danger')
                            ->placeholder('-'),
                    ])
                    ->columns(5),
                Infolists\Components\Section::make('Timing')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('queued_at')
                            ->dateTime()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('started_at')
                            ->dateTime()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->dateTime()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('duration')
                            ->label('Duration')
                            ->getStateUsing(function ($record) {
                                if (!$record->started_at) return null;
                                $end = $record->completed_at ?? now();
                                return $record->started_at->diffForHumans($end, ['parts' => 2, 'short' => true]);
                            })
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('retry_count')
                            ->label('Retry Count')
                            ->badge()
                            ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Payload')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('payload')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
                Infolists\Components\Section::make('Output')
                    ->schema([
                        Infolists\Components\TextEntry::make('output')
                            ->markdown()
                            ->columnSpanFull()
                            ->placeholder('No output'),
                    ])
                    ->collapsible()
                    ->collapsed(fn ($record) => empty($record->output)),
                Infolists\Components\Section::make('Error')
                    ->schema([
                        Infolists\Components\TextEntry::make('error')
                            ->markdown()
                            ->columnSpanFull()
                            ->placeholder('No errors'),
                    ])
                    ->collapsible()
                    ->collapsed(fn ($record) => empty($record->error)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'queued' => 'info',
                        'running' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('exit_code')
                    ->badge()
                    ->color(fn ($state): string => $state === null ? 'gray' : ($state === 0 ? 'success' : 'danger'))
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(function (AgentJob $record) {
                        if (!$record->started_at) return null;
                        $end = $record->completed_at ?? now();
                        $diff = $record->started_at->diffInSeconds($end);
                        if ($diff < 60) return "{$diff}s";
                        if ($diff < 3600) return round($diff / 60, 1) . 'm';
                        return round($diff / 3600, 1) . 'h';
                    })
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('retry_count')
                    ->label('Retries')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        AgentJob::STATUS_PENDING => 'Pending',
                        AgentJob::STATUS_QUEUED => 'Queued',
                        AgentJob::STATUS_RUNNING => 'Running',
                        AgentJob::STATUS_COMPLETED => 'Completed',
                        AgentJob::STATUS_FAILED => 'Failed',
                        AgentJob::STATUS_CANCELLED => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options(fn () => AgentJob::where('team_id', Filament::getTenant()?->id)
                        ->distinct()
                        ->pluck('type', 'type')
                        ->toArray()),
                Tables\Filters\SelectFilter::make('server_id')
                    ->relationship('server', 'name')
                    ->label('Server'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (AgentJob $record) => in_array($record->status, [
                        AgentJob::STATUS_PENDING,
                        AgentJob::STATUS_QUEUED,
                        AgentJob::STATUS_RUNNING,
                    ]))
                    ->requiresConfirmation()
                    ->action(fn (AgentJob $record) => $record->update(['status' => AgentJob::STATUS_CANCELLED])),
                Tables\Actions\Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (AgentJob $record) => $record->isFailed() && $record->canRetry())
                    ->action(function (AgentJob $record) {
                        $record->update([
                            'status' => AgentJob::STATUS_PENDING,
                            'retry_count' => $record->retry_count + 1,
                            'error' => null,
                            'output' => null,
                            'exit_code' => null,
                            'started_at' => null,
                            'completed_at' => null,
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => false), // Disable bulk delete for audit trail
                ]),
            ])
            ->poll('5s');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgentJobs::route('/'),
            'view' => Pages\ViewAgentJob::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('team_id', Filament::getTenant()?->id);
    }

    public static function canCreate(): bool
    {
        return false; // Jobs are created programmatically, not by users
    }
}
