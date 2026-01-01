<?php

namespace App\Filament\Resources\ServerResource\RelationManagers;

use App\Filament\Resources\WebAppResource;
use App\Models\WebApp;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class WebAppsRelationManager extends RelationManager
{
    protected static string $relationship = 'webApps';

    protected static ?string $title = 'Web Applications';

    protected static ?string $icon = 'heroicon-o-globe-alt';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => WebApp::STATUS_PENDING,
                        'info' => WebApp::STATUS_CREATING,
                        'success' => WebApp::STATUS_ACTIVE,
                        'danger' => [WebApp::STATUS_SUSPENDED, WebApp::STATUS_DELETING],
                    ]),
                Tables\Columns\TextColumn::make('php_version')
                    ->label('PHP')
                    ->badge(),
                Tables\Columns\BadgeColumn::make('ssl_status')
                    ->label('SSL')
                    ->colors([
                        'gray' => WebApp::SSL_NONE,
                        'warning' => WebApp::SSL_PENDING,
                        'success' => WebApp::SSL_ACTIVE,
                        'danger' => WebApp::SSL_FAILED,
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        WebApp::STATUS_PENDING => 'Pending',
                        WebApp::STATUS_CREATING => 'Creating',
                        WebApp::STATUS_ACTIVE => 'Active',
                        WebApp::STATUS_SUSPENDED => 'Suspended',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label('Create Web App')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => WebAppResource::getUrl('create')),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (WebApp $record) => WebAppResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No web applications')
            ->emptyStateDescription('Create your first web application on this server.')
            ->emptyStateIcon('heroicon-o-globe-alt');
    }
}
