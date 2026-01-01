<?php

namespace App\Filament\Widgets;

use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity;

class RecentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Activity';

    public function table(Table $table): Table
    {
        $teamId = Filament::getTenant()?->id;

        return $table
            ->query(
                Activity::query()
                    ->where(function ($query) use ($teamId) {
                        $query->where('properties->team_id', $teamId)
                            ->orWhere('properties->attributes->team_id', $teamId);
                    })
                    ->latest()
            )
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
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Resource')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('properties.attributes')
                    ->label('Changes')
                    ->formatStateUsing(function ($state) {
                        if (!$state || !is_array($state)) {
                            return '—';
                        }
                        $keys = array_keys($state);
                        $display = array_slice($keys, 0, 3);
                        $more = count($keys) > 3 ? ' +' . (count($keys) - 3) : '';
                        return implode(', ', $display) . $more;
                    })
                    ->limit(40),
            ])
            ->paginated([5])
            ->defaultSort('created_at', 'desc');
    }
}
