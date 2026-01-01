<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolResource\Pages;
use App\Models\Tool;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ToolResource extends Resource
{
    protected static ?string $model = Tool::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Tools';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Tool')
                    ->icon(fn (Tool $record) => $record->icon)
                    ->iconColor('primary')
                    ->searchable(['name']),
                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('path')
                    ->label('Path')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Seen')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('server_id')
            ->groups([
                Tables\Grouping\Group::make('server.name')
                    ->label('Server')
                    ->collapsible(),
            ])
            ->defaultGroup('server.name')
            ->filters([
                Tables\Filters\SelectFilter::make('server_id')
                    ->relationship('server', 'name')
                    ->label('Server'),
                Tables\Filters\SelectFilter::make('name')
                    ->options(Tool::getToolDisplayNames())
                    ->label('Tool'),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No tools detected')
            ->emptyStateDescription('Tools will appear here after the agent reports them via heartbeat.')
            ->emptyStateIcon('heroicon-o-wrench');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTools::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return Tool::query()
            ->whereHas('server', fn (Builder $query) =>
                $query->where('team_id', Filament::getTenant()?->id));
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
