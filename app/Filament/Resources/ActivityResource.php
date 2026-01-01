<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use Filament\Facades\Filament;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 99;

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $modelLabel = 'Activity';

    protected static ?string $pluralModelLabel = 'Activities';

    // Disable tenant ownership check - we handle scoping manually in getEloquentQuery()
    protected static ?string $tenantOwnershipRelationshipName = null;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Activity Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label('Action'),
                        Infolists\Components\TextEntry::make('log_name')
                            ->label('Log'),
                        Infolists\Components\TextEntry::make('event')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'info',
                                'deleted' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Subject')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject_type')
                            ->label('Type')
                            ->formatStateUsing(fn ($state) => class_basename($state)),
                        Infolists\Components\TextEntry::make('subject_id')
                            ->label('ID')
                            ->copyable(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->subject_type),

                Infolists\Components\Section::make('Performed By')
                    ->schema([
                        Infolists\Components\TextEntry::make('causer.name')
                            ->label('User'),
                        Infolists\Components\TextEntry::make('causer.email')
                            ->label('Email'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->causer_id),

                Infolists\Components\Section::make('Changes')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('properties.old')
                            ->label('Old Values')
                            ->visible(fn ($record) => !empty($record->properties['old'])),
                        Infolists\Components\KeyValueEntry::make('properties.attributes')
                            ->label('New Values')
                            ->visible(fn ($record) => !empty($record->properties['attributes'])),
                    ])
                    ->visible(fn ($record) => !empty($record->properties)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('log_name')
                    ->label('Log')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->badge(),
                Tables\Columns\TextColumn::make('event')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\ImageColumn::make('causer.profile_photo_url')
                    ->label('')
                    ->circular()
                    ->width(24)
                    ->height(24)
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->causer?->name ?? 'S') . '&color=7F9CF5&background=EBF4FF'),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('By')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable(),
            ])
            ->poll('10s')
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Log')
                    ->options(fn () => Activity::query()
                        ->distinct()
                        ->pluck('log_name', 'log_name')
                        ->toArray()
                    ),
                Tables\Filters\SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from'),
                        \Filament\Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        $teamId = Filament::getTenant()?->id;
                        $activities = Activity::query()
                            ->where(function (Builder $query) use ($teamId) {
                                $query->where('properties->team_id', $teamId)
                                    ->orWhere('properties->attributes->team_id', $teamId);
                            })
                            ->orderBy('created_at', 'desc')
                            ->limit(1000)
                            ->get();

                        $csv = "Date,Action,Subject,Event,User\n";
                        foreach ($activities as $activity) {
                            $csv .= sprintf(
                                "%s,%s,%s,%s,%s\n",
                                $activity->created_at->format('Y-m-d H:i:s'),
                                str_replace(',', ' ', $activity->description),
                                class_basename($activity->subject_type ?? ''),
                                $activity->event ?? '',
                                $activity->causer?->name ?? 'System'
                            );
                        }

                        return response()->streamDownload(function () use ($csv) {
                            echo $csv;
                        }, 'activity-log-' . now()->format('Y-m-d') . '.csv');
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'view' => Pages\ViewActivity::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $teamId = Filament::getTenant()?->id;

        // Bypass parent::getEloquentQuery() to avoid tenant ownership check
        // since Activity model doesn't have a team relationship
        return Activity::query()
            ->where(function (Builder $query) use ($teamId) {
                $query->where('properties->team_id', $teamId)
                    ->orWhere('properties->attributes->team_id', $teamId);
            });
    }
}
