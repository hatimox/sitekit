<?php

namespace App\Filament\Pages;

use App\Models\SourceProvider;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SourceProviders extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-code-bracket';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.source-providers';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SourceProvider::query()
                    ->where('team_id', Filament::getTenant()?->id)
            )
            ->columns([
                TextColumn::make('provider')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'github' => 'gray',
                        'gitlab' => 'warning',
                        'bitbucket' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('provider_username')
                    ->label('Username')
                    ->copyable(),
                TextColumn::make('token_expires_at')
                    ->label('Token Expires')
                    ->dateTime()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->label('Connected')
                    ->since(),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('disconnect')
                    ->label('Disconnect')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (SourceProvider $record) {
                        if ($record->webApps()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Disconnect')
                                ->body('This provider is in use by web applications.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $record->delete();

                        \Filament\Notifications\Notification::make()
                            ->title('Provider Disconnected')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function getConnectUrl(string $provider): string
    {
        return route('oauth.redirect', ['provider' => $provider]);
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
