<?php

namespace App\Filament\Resources\WebAppResource\RelationManagers;

use App\Models\HealthCheck;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HealthChecksRelationManager extends RelationManager
{
    protected static string $relationship = 'healthChecks';

    protected static ?string $title = 'Health Checks';

    protected static ?string $icon = 'heroicon-o-heart';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Main Website'),
                Forms\Components\TextInput::make('url')
                    ->required()
                    ->url()
                    ->placeholder('https://example.com/health')
                    ->helperText('URL to check for availability'),
                Forms\Components\Select::make('method')
                    ->options([
                        'GET' => 'GET',
                        'HEAD' => 'HEAD',
                        'POST' => 'POST',
                    ])
                    ->default('GET'),
                Forms\Components\TextInput::make('expected_status')
                    ->numeric()
                    ->default(200)
                    ->helperText('Expected HTTP status code'),
                Forms\Components\TextInput::make('expected_content')
                    ->placeholder('OK')
                    ->helperText('Optional: String that must be present in response'),
                Forms\Components\Select::make('interval_minutes')
                    ->options([
                        1 => 'Every 1 minute',
                        5 => 'Every 5 minutes',
                        10 => 'Every 10 minutes',
                        15 => 'Every 15 minutes',
                        30 => 'Every 30 minutes',
                        60 => 'Every hour',
                    ])
                    ->default(5),
                Forms\Components\TextInput::make('timeout_seconds')
                    ->numeric()
                    ->default(30)
                    ->minValue(5)
                    ->maxValue(120),
                Forms\Components\Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->default(true),
                Forms\Components\Toggle::make('notify_on_failure')
                    ->label('Notify on failure')
                    ->default(true),
                Forms\Components\Toggle::make('notify_on_recovery')
                    ->label('Notify on recovery')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('url')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => HealthCheck::STATUS_UP,
                        'danger' => HealthCheck::STATUS_DOWN,
                        'warning' => HealthCheck::STATUS_PENDING,
                    ]),
                Tables\Columns\TextColumn::make('uptime_percentage')
                    ->label('Uptime (24h)')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}%" : '-')
                    ->color(fn ($state) => match (true) {
                        $state >= 99 => 'success',
                        $state >= 95 => 'warning',
                        $state > 0 => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('last_response_time_ms')
                    ->label('Response Time')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}ms" : '-'),
                Tables\Columns\TextColumn::make('last_checked_at')
                    ->label('Last Check')
                    ->since(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        HealthCheck::STATUS_UP => 'Up',
                        HealthCheck::STATUS_DOWN => 'Down',
                        HealthCheck::STATUS_PENDING => 'Pending',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['team_id'] = $this->ownerRecord->team_id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('check_now')
                    ->label('Check Now')
                    ->icon('heroicon-o-play')
                    ->action(function (HealthCheck $record) {
                        $log = $record->performCheck();
                        Notification::make()
                            ->title($log->isUp() ? 'Health Check Passed' : 'Health Check Failed')
                            ->body($log->isUp()
                                ? "Response time: {$log->response_time_ms}ms"
                                : $log->error)
                            ->color($log->isUp() ? 'success' : 'danger')
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
