<?php

namespace App\Filament\Resources\ServerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Activity Log';

    protected static ?string $icon = 'heroicon-o-clock';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('M j, H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('properties.attributes')
                    ->label('Changes')
                    ->formatStateUsing(function ($state) {
                        if (!$state || !is_array($state)) {
                            return '-';
                        }
                        $keys = array_keys($state);
                        $display = array_slice($keys, 0, 3);
                        $more = count($keys) > 3 ? ' +' . (count($keys) - 3) : '';
                        return implode(', ', $display) . $more;
                    })
                    ->limit(40),
            ])
            ->paginated([5, 10, 25])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No activity yet')
            ->emptyStateDescription('Activity will appear here when changes are made.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
