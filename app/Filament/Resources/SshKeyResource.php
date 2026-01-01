<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresServerForNavigation;
use App\Filament\Resources\SshKeyResource\Pages;
use App\Models\Server;
use App\Models\SshKey;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SshKeyResource extends Resource
{
    use RequiresServerForNavigation;

    protected static ?string $model = SshKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'SSH Keys';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('SSH Key Details')
                    ->description('Add your SSH public key to enable secure, passwordless access to your servers.')
                    ->icon('heroicon-o-key')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('ai_key_type')
                            ->label('Which key type?')
                            ->icon('heroicon-m-sparkles')
                            ->color('primary')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("Which SSH key type should I use? Compare RSA, ED25519, ECDSA, and DSA. What are the security implications and compatibility of each? What do you recommend for modern servers?")',
                            ]),
                        Forms\Components\Actions\Action::make('ai_generate')
                            ->label('How to generate?')
                            ->icon('heroicon-m-sparkles')
                            ->color('gray')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("How do I generate a new SSH key pair? Show me the commands for both ED25519 (recommended) and RSA. Also explain where the keys are stored and how to add them to ssh-agent.")',
                            ]),
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('My Laptop Key')
                            ->helperText('A memorable name like "MacBook Pro" or "Work Desktop"'),
                        Forms\Components\Textarea::make('public_key')
                            ->label('Public Key')
                            ->required()
                            ->rows(5)
                            ->placeholder('ssh-rsa AAAA... or ssh-ed25519 AAAA...')
                            ->helperText('Find yours with: cat ~/.ssh/id_ed25519.pub or cat ~/.ssh/id_rsa.pub')
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                if (!SshKey::isValidPublicKey($value)) {
                                    $fail('Please enter a valid SSH public key.');
                                }
                            }),
                        Forms\Components\Placeholder::make('fingerprint_display')
                            ->label('Fingerprint')
                            ->content(fn (?SshKey $record) => $record?->fingerprint ?? 'Will be calculated on save')
                            ->hiddenOn('create'),
                    ]),

                Forms\Components\Section::make('Deploy to Servers')
                    ->description('Optionally deploy this key to servers immediately after creation.')
                    ->icon('heroicon-o-server-stack')
                    ->schema([
                        Forms\Components\Select::make('deploy_servers')
                            ->label('Servers')
                            ->multiple()
                            ->options(fn () => Server::where('team_id', Filament::getTenant()?->id)
                                ->where('status', Server::STATUS_ACTIVE)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to add key without deploying. You can deploy later from the list.'),
                        Forms\Components\Select::make('target_user')
                            ->label('Target User')
                            ->options([
                                'sitekit' => 'sitekit (recommended)',
                                'root' => 'root',
                            ])
                            ->default('sitekit')
                            ->helperText('The system user whose authorized_keys will receive this key. Use "sitekit" for deployments, "root" for system administration.'),
                    ])
                    ->columns(2)
                    ->visibleOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('key_type')
                    ->label('Type')
                    ->badge()
                    ->getStateUsing(function (SshKey $record) {
                        $key = trim($record->public_key);
                        if (str_starts_with($key, 'ssh-ed25519')) return 'ED25519';
                        if (str_starts_with($key, 'ssh-rsa')) return 'RSA';
                        if (str_starts_with($key, 'ecdsa-')) return 'ECDSA';
                        if (str_starts_with($key, 'ssh-dss')) return 'DSA';
                        return 'Unknown';
                    })
                    ->color(fn ($state) => match ($state) {
                        'ED25519' => 'success',
                        'RSA' => 'info',
                        'ECDSA' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('fingerprint')
                    ->searchable()
                    ->fontFamily('mono')
                    ->size('sm')
                    ->limit(30)
                    ->tooltip(fn (SshKey $record) => $record->fingerprint)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Added By')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('servers_count')
                    ->label('Deployed To')
                    ->counts('servers')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state} server(s)" : 'Not deployed')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->since()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                // AI Help for SSH key
                Tables\Actions\Action::make('ai_help')
                    ->label('AI Help')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->visible(fn () => config('ai.enabled'))
                    ->url('#')
                    ->extraAttributes(fn (SshKey $record) => [
                        'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("Tell me about my SSH key: Name: ' . e($record->name) . ', Type: ' . (str_starts_with(trim($record->public_key), 'ssh-ed25519') ? 'ED25519' : (str_starts_with(trim($record->public_key), 'ssh-rsa') ? 'RSA' : 'Unknown')) . ', Created: ' . $record->created_at->toDateString() . '. Is this key type still secure? Should I consider upgrading? What are best practices for SSH key management?")',
                    ]),

                Tables\Actions\Action::make('deploy')
                    ->label('Deploy to Server')
                    ->icon('heroicon-o-server-stack')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('server_id')
                            ->label('Server')
                            ->options(fn () => Server::where('team_id', Filament::getTenant()?->id)
                                ->where('status', Server::STATUS_ACTIVE)
                                ->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('username')
                            ->label('Target User')
                            ->options([
                                'sitekit' => 'sitekit (recommended)',
                                'root' => 'root',
                            ])
                            ->default('sitekit')
                            ->required()
                            ->helperText('SSH as: ssh <user>@server-ip'),
                    ])
                    ->action(function (SshKey $record, array $data) {
                        $server = Server::find($data['server_id']);
                        $record->dispatchAddToServer($server, $data['username']);
                        Notification::make()
                            ->title('SSH key deployment queued')
                            ->body("Key will be added to {$server->name} for user {$data['username']}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('deployAll')
                    ->label('Deploy to All')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('username')
                            ->label('Target User')
                            ->options([
                                'sitekit' => 'sitekit (recommended)',
                                'root' => 'root',
                            ])
                            ->default('sitekit')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Deploy to All Servers')
                    ->modalDescription('This will deploy the SSH key to all active servers in your team.')
                    ->action(function (SshKey $record, array $data) {
                        $record->dispatchToAllServers($data['username']);
                        Notification::make()
                            ->title('SSH key deployment queued')
                            ->body('Key will be deployed to all active servers')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('ai_security_review')
                        ->label('AI Security Review')
                        ->icon('heroicon-o-sparkles')
                        ->color('primary')
                        ->visible(fn () => config('ai.enabled'))
                        ->requiresConfirmation()
                        ->modalHeading('AI Security Review')
                        ->modalDescription(fn ($records) => 'Review ' . $records->count() . ' SSH key(s) for security best practices?')
                        ->modalSubmitActionLabel('Review with AI')
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records, $livewire) {
                            $keyList = $records->map(fn ($k) => "- {$k->name}: " . (str_starts_with(trim($k->public_key), 'ssh-ed25519') ? 'ED25519' : (str_starts_with(trim($k->public_key), 'ssh-rsa') ? 'RSA' : 'Unknown')) . " (created " . $k->created_at->toDateString() . ")")->join("\n");
                            $message = "Review these SSH keys for security:\n\n{$keyList}\n\nCheck if any key types are outdated or insecure. Suggest improvements and best practices for SSH key management.";
                            $livewire->dispatch('open-ai-chat', message: $message);
                        }),

                    Tables\Actions\BulkAction::make('deployAllBulk')
                        ->label('Deploy Selected to All Servers')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->form([
                            Forms\Components\Select::make('username')
                                ->label('Target User')
                                ->options([
                                    'sitekit' => 'sitekit (recommended)',
                                    'root' => 'root',
                                ])
                                ->default('sitekit')
                                ->required(),
                        ])
                        ->requiresConfirmation()
                        ->action(function ($records, array $data) {
                            foreach ($records as $record) {
                                $record->dispatchToAllServers($data['username']);
                            }
                            Notification::make()
                                ->title('SSH keys deployment queued')
                                ->body('Selected keys will be deployed to all active servers')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-key')
            ->emptyStateHeading('No SSH keys added')
            ->emptyStateDescription('Add your SSH public key to enable secure, passwordless access to your servers. The key will be deployed to all current and future servers.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add SSH Key')
                    ->icon('heroicon-o-plus'),
            ]);
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
            'index' => Pages\ListSshKeys::route('/'),
            'create' => Pages\CreateSshKey::route('/create'),
            'view' => Pages\ViewSshKey::route('/{record}'),
            'edit' => Pages\EditSshKey::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('team_id', Filament::getTenant()?->id);
    }
}
