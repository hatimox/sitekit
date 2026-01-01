<?php

namespace App\Filament\Resources\ServerResource\RelationManagers;

use App\Filament\Resources\DatabaseResource;
use App\Models\Database;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DatabasesRelationManager extends RelationManager
{
    protected static string $relationship = 'databases';

    protected static ?string $title = 'Databases';

    protected static ?string $icon = 'heroicon-o-circle-stack';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Database::TYPE_MARIADB => 'success',
                        Database::TYPE_MYSQL => 'info',
                        Database::TYPE_POSTGRESQL => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => Database::STATUS_PENDING,
                        'success' => Database::STATUS_ACTIVE,
                        'danger' => Database::STATUS_FAILED,
                    ]),
                Tables\Columns\TextColumn::make('webApp.name')
                    ->label('Web App')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        Database::TYPE_MARIADB => 'MariaDB',
                        Database::TYPE_MYSQL => 'MySQL',
                        Database::TYPE_POSTGRESQL => 'PostgreSQL',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label('Create Database')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => DatabaseResource::getUrl('create')),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Database $record) => DatabaseResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No databases')
            ->emptyStateDescription('Create your first database on this server.')
            ->emptyStateIcon('heroicon-o-circle-stack');
    }
}
