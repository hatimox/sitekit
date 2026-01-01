<?php

namespace App\Filament\Resources\DatabaseResource\RelationManagers;

use App\Models\AgentJob;
use App\Models\DatabaseUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $recordTitleAttribute = 'username';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->maxLength(32)
                    ->regex('/^[a-zA-Z][a-zA-Z0-9_]*$/')
                    ->helperText('Must start with a letter, only letters, numbers, and underscores allowed')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) =>
                        $rule->where('database_id', $this->ownerRecord->id)),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn ($context) => $context === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->default(fn () => Str::random(24))
                    ->helperText('Leave empty to keep current password'),
                Forms\Components\Toggle::make('can_remote')
                    ->label('Allow remote connections')
                    ->helperText('If enabled, this user can connect from any host')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\IconColumn::make('can_remote')
                    ->label('Remote Access')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function (DatabaseUser $record) {
                        $database = $this->ownerRecord;
                        AgentJob::create([
                            'server_id' => $database->server_id,
                            'team_id' => $database->team_id,
                            'type' => 'create_database_user',
                            'payload' => [
                                'database_user_id' => $record->id,
                                'db_name' => $database->name,
                                'username' => $record->username,
                                'password' => $record->password,
                                'can_remote' => $record->can_remote,
                                'type' => $database->type,
                            ],
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->default(fn () => Str::random(24)),
                    ])
                    ->action(function (DatabaseUser $record, array $data) {
                        $database = $this->ownerRecord;
                        $record->update(['password' => $data['new_password']]);

                        AgentJob::create([
                            'server_id' => $database->server_id,
                            'team_id' => $database->team_id,
                            'type' => 'create_database_user',
                            'payload' => [
                                'database_user_id' => $record->id,
                                'db_name' => $database->name,
                                'username' => $record->username,
                                'password' => $data['new_password'],
                                'can_remote' => $record->can_remote,
                                'type' => $database->type,
                                'update_password' => true,
                            ],
                        ]);

                        Notification::make()
                            ->title('Password reset queued')
                            ->body('The password will be updated on the server.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->after(function (DatabaseUser $record) {
                        $database = $this->ownerRecord;
                        AgentJob::create([
                            'server_id' => $database->server_id,
                            'team_id' => $database->team_id,
                            'type' => 'create_database_user',
                            'payload' => [
                                'database_user_id' => $record->id,
                                'db_name' => $database->name,
                                'username' => $record->username,
                                'password' => $record->password,
                                'can_remote' => $record->can_remote,
                                'type' => $database->type,
                                'update_only' => true,
                            ],
                        ]);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (DatabaseUser $record) {
                        $database = $this->ownerRecord;
                        AgentJob::create([
                            'server_id' => $database->server_id,
                            'team_id' => $database->team_id,
                            'type' => 'delete_database_user',
                            'payload' => [
                                'database_user_id' => $record->id,
                                'db_name' => $database->name,
                                'username' => $record->username,
                                'type' => $database->type,
                            ],
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
