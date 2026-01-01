<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresServerForNavigation;
use App\Filament\Resources\DatabaseResource\Pages;
use App\Filament\Resources\DatabaseResource\RelationManagers;
use App\Models\Database;
use App\Models\Service;
use App\Models\WebApp;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DatabaseResource extends Resource
{
    use RequiresServerForNavigation;

    protected static ?string $model = Database::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationGroup = 'Applications';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Databases';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('team_id', Filament::getTenant()?->id)
            ->where('status', Database::STATUS_ACTIVE)
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Database Details')
                    ->description('Create a new database with a user. The database will be created on the selected server.')
                    ->icon('heroicon-o-circle-stack')
                    ->schema([
                        Forms\Components\Select::make('server_id')
                            ->relationship('server', 'name', fn (Builder $query) =>
                                $query->where('team_id', Filament::getTenant()?->id)
                                    ->where('status', 'active'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->helperText('Only active servers with database services are shown'),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->alphaDash()
                            ->maxLength(64)
                            ->placeholder('my_database')
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule, $get) {
                                return $rule->where('server_id', $get('server_id'));
                            })
                            ->helperText('Letters, numbers, dashes, and underscores only'),
                        Forms\Components\Select::make('type')
                            ->options(function (Get $get) {
                                $serverId = $get('server_id');
                                if (!$serverId) {
                                    return []; // No server selected
                                }

                                // Get active database engine services on this server
                                $activeEngines = Service::where('server_id', $serverId)
                                    ->where('status', Service::STATUS_ACTIVE)
                                    ->whereIn('type', [Service::TYPE_MARIADB, Service::TYPE_MYSQL, Service::TYPE_POSTGRESQL])
                                    ->pluck('type')
                                    ->toArray();

                                $options = [];
                                if (in_array(Service::TYPE_MARIADB, $activeEngines)) {
                                    $options[Database::TYPE_MARIADB] = 'MariaDB (Recommended)';
                                }
                                if (in_array(Service::TYPE_MYSQL, $activeEngines)) {
                                    $options[Database::TYPE_MYSQL] = 'MySQL';
                                }
                                if (in_array(Service::TYPE_POSTGRESQL, $activeEngines)) {
                                    $options[Database::TYPE_POSTGRESQL] = 'PostgreSQL';
                                }

                                return $options;
                            })
                            ->default(Database::TYPE_MARIADB)
                            ->required()
                            ->helperText(fn (Get $get) => $get('server_id')
                                ? 'Only engines that are running on this server are shown'
                                : 'Select a server first'),
                        Forms\Components\Select::make('web_app_id')
                            ->label('Link to Web App')
                            ->options(fn (Get $get) => WebApp::where('server_id', $get('server_id'))->pluck('name', 'id'))
                            ->searchable()
                            ->helperText('Links database to an app for easy reference'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Database User')
                    ->description('Create a dedicated user with access to this database. Save these credentials securely.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Toggle::make('create_user')
                            ->label('Create database user')
                            ->default(true)
                            ->reactive()
                            ->dehydrated(false)
                            ->helperText('Recommended: Create a dedicated user for each database'),
                        Forms\Components\TextInput::make('db_username')
                            ->label('Username')
                            ->visible(fn (Get $get) => $get('create_user'))
                            ->default(fn () => 'user_' . Str::random(8))
                            ->required(fn (Get $get) => $get('create_user'))
                            ->dehydrated(false)
                            ->helperText('Auto-generated. You can customize this.'),
                        Forms\Components\TextInput::make('db_password')
                            ->label('Password')
                            ->visible(fn (Get $get) => $get('create_user'))
                            ->default(fn () => Str::random(24))
                            ->password()
                            ->revealable()
                            ->required(fn (Get $get) => $get('create_user'))
                            ->dehydrated(false)
                            ->helperText('Copy this password now - it cannot be retrieved later'),
                    ])
                    ->columns(3)
                    ->visibleOn('create'),

                Forms\Components\Section::make('Backup Schedule')
                    ->description('Configure automatic backups for disaster recovery. Backups can be stored locally or uploaded to cloud storage.')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('ai_backup_help')
                            ->label('Backup strategy')
                            ->icon('heroicon-m-sparkles')
                            ->color('primary')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("What backup schedule should I use for my database? Explain daily vs hourly backups, retention policies, and how to choose based on data change frequency. Also explain RPO (Recovery Point Objective) and RTO (Recovery Time Objective).")',
                            ]),
                    ])
                    ->schema([
                        Forms\Components\Toggle::make('backup_enabled')
                            ->label('Enable automated backups')
                            ->reactive()
                            ->helperText('Highly recommended for production databases'),
                        Forms\Components\Select::make('backup_schedule')
                            ->label('Schedule')
                            ->visible(fn (Get $get) => $get('backup_enabled'))
                            ->options([
                                '0 0 * * *' => 'Daily at midnight',
                                '0 */6 * * *' => 'Every 6 hours',
                                '0 */12 * * *' => 'Every 12 hours',
                                '0 0 * * 0' => 'Weekly on Sunday',
                                '0 0 1 * *' => 'Monthly on the 1st',
                            ])
                            ->required(fn (Get $get) => $get('backup_enabled'))
                            ->helperText('Choose based on how frequently your data changes'),
                        Forms\Components\TextInput::make('backup_retention_days')
                            ->label('Retention (days)')
                            ->visible(fn (Get $get) => $get('backup_enabled'))
                            ->numeric()
                            ->default(7)
                            ->minValue(1)
                            ->maxValue(365)
                            ->helperText('Older backups are automatically deleted'),
                    ])
                    ->columns(3)
                    ->visibleOn('edit'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Database Details')
                    ->description('Core database information and current status.')
                    ->icon('heroicon-o-circle-stack')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'mysql' => 'info',
                                'mariadb' => 'success',
                                'postgresql' => 'primary',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'gray',
                                'active' => 'success',
                                'failed' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('server.name')
                            ->label('Server'),
                        Infolists\Components\TextEntry::make('webApp.name')
                            ->label('Web App')
                            ->placeholder('Not linked'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Connection Details')
                    ->icon('heroicon-o-link')
                    ->description('Use these details to connect from your application or database client like TablePlus, DBeaver, or phpMyAdmin.')
                    ->schema([
                        Infolists\Components\TextEntry::make('host')
                            ->label('Host')
                            ->copyable()
                            ->helperText('Usually 127.0.0.1 for local connections'),
                        Infolists\Components\TextEntry::make('port')
                            ->label('Port')
                            ->copyable()
                            ->helperText('Default: 3306 (MySQL/MariaDB) or 5432 (PostgreSQL)'),
                        Infolists\Components\TextEntry::make('name')
                            ->label('Database Name')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('connection_string')
                            ->label('Connection String')
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull()
                            ->helperText('For .env files: Replace [username] and [password] with your credentials'),
                        Infolists\Components\TextEntry::make('connection_command')
                            ->label('CLI Command')
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull()
                            ->helperText('SSH into server and run this to access database directly'),
                    ])
                    ->columns(3)
                    ->collapsible(),
                Infolists\Components\Section::make('Error')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->schema([
                        Infolists\Components\TextEntry::make('error_message')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => !empty($record->error_message)),

                Infolists\Components\Section::make('Backup Schedule')
                    ->description('Automated backup configuration. Enable backups for production databases.')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->schema([
                        Infolists\Components\IconEntry::make('backup_enabled')
                            ->label('Enabled')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('backup_schedule')
                            ->label('Schedule')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                '0 0 * * *' => 'Daily at midnight',
                                '0 */6 * * *' => 'Every 6 hours',
                                '0 */12 * * *' => 'Every 12 hours',
                                '0 0 * * 0' => 'Weekly on Sunday',
                                '0 0 1 * *' => 'Monthly on the 1st',
                                default => $state ?? 'Not configured',
                            }),
                        Infolists\Components\TextEntry::make('backup_retention_days')
                            ->label('Retention')
                            ->suffix(' days'),
                        Infolists\Components\TextEntry::make('last_backup_at')
                            ->label('Last Backup')
                            ->dateTime()
                            ->placeholder('Never - enable backups to protect your data'),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('size_mb')
                    ->label('Size')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        if ($state < 1024) return round($state, 1) . ' MB';
                        return round($state / 1024, 2) . ' GB';
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('backup_enabled')
                    ->label('Backup')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_backup_at')
                    ->label('Last Backup')
                    ->since()
                    ->placeholder('Never')
                    ->color(fn ($record) => $record->last_backup_at && $record->last_backup_at->diffInDays(now()) > 7 ? 'danger' : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('server_id')
                    ->relationship('server', 'name')
                    ->label('Server'),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        Database::TYPE_MARIADB => 'MariaDB',
                        Database::TYPE_MYSQL => 'MySQL',
                        Database::TYPE_POSTGRESQL => 'PostgreSQL',
                    ]),
            ])
            ->actions([
                // AI Troubleshoot for failed databases
                Tables\Actions\Action::make('ai_troubleshoot')
                    ->label('AI Help')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->visible(fn (Database $record) => $record->status === Database::STATUS_FAILED && config('ai.enabled'))
                    ->url('#')
                    ->extraAttributes(fn (Database $record) => [
                        'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("My ' . e($record->type) . ' database \'' . e($record->name) . '\' on server \'' . e($record->server?->name) . '\' has failed. Error: ' . e($record->error_message ?? 'Unknown') . '. How do I fix this?")',
                    ]),

                // AI Optimize for active databases
                Tables\Actions\Action::make('ai_optimize')
                    ->label('AI Optimize')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->visible(fn (Database $record) => $record->status === Database::STATUS_ACTIVE && config('ai.enabled'))
                    ->url('#')
                    ->extraAttributes(fn (Database $record) => [
                        'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("Optimize my ' . e($record->type) . ' database \'' . e($record->name) . '\'. Size: ' . ($record->size_mb ? e($record->size_mb) . ' MB' : 'Unknown') . '. Suggest query optimization, indexing, and configuration tuning.")',
                    ]),

                Tables\Actions\Action::make('backup')
                    ->label('Backup')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->visible(fn (Database $record) => $record->status === Database::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->modalDescription('This will create an immediate backup of your database.')
                    ->action(function (Database $record) {
                        $record->dispatchJob('backup_database', [
                            'database_name' => $record->name,
                            'type' => $record->type,
                        ]);
                        Notification::make()
                            ->title('Backup started')
                            ->body('Database backup job has been queued.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('import')
                    ->label('Import')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (Database $record) => $record->status === Database::STATUS_ACTIVE)
                    ->form([
                        Forms\Components\FileUpload::make('sql_file')
                            ->label('SQL File')
                            ->required()
                            ->acceptedFileTypes(['application/sql', 'text/plain', '.sql', '.gz'])
                            ->helperText('Upload a .sql or .sql.gz file to import'),
                    ])
                    ->action(function (Database $record, array $data) {
                        $record->dispatchJob('import_database', [
                            'database_name' => $record->name,
                            'type' => $record->type,
                            'file_path' => $data['sql_file'],
                        ]);
                        Notification::make()
                            ->title('Import started')
                            ->body('Database import job has been queued.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (Database $record) => $record->status === Database::STATUS_ACTIVE)
                    ->action(function (Database $record) {
                        $record->dispatchJob('export_database', [
                            'database_name' => $record->name,
                            'type' => $record->type,
                        ]);
                        Notification::make()
                            ->title('Export started')
                            ->body('Database export job has been queued.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('optimize')
                    ->label('Optimize')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->visible(fn (Database $record) => $record->status === Database::STATUS_ACTIVE && $record->type !== Database::TYPE_POSTGRESQL)
                    ->requiresConfirmation()
                    ->action(function (Database $record) {
                        $record->dispatchJob('optimize_database', [
                            'database_name' => $record->name,
                            'type' => $record->type,
                        ]);
                        Notification::make()
                            ->title('Optimization started')
                            ->body('Database optimization job has been queued.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Database $record) {
                        $record->dispatchJob('delete_database', [
                            'database_name' => $record->name,
                            'type' => $record->type,
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-circle-stack')
            ->emptyStateHeading('No databases yet')
            ->emptyStateDescription('Create a MariaDB, MySQL, or PostgreSQL database for your applications. Each database includes a dedicated user for secure access.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Database')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
            RelationManagers\BackupsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDatabases::route('/'),
            'create' => Pages\CreateDatabase::route('/create'),
            'view' => Pages\ViewDatabase::route('/{record}'),
            'edit' => Pages\EditDatabase::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('team_id', Filament::getTenant()?->id);
    }
}
