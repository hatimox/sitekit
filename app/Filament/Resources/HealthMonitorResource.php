<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HealthMonitorResource\Pages;
use App\Models\HealthMonitor;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HealthMonitorResource extends Resource
{
    protected static ?string $model = HealthMonitor::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Health Monitors';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $downCount = static::getModel()::where('team_id', Filament::getTenant()?->id)
            ->where('status', HealthMonitor::STATUS_DOWN)
            ->count();
        return $downCount > 0 ? (string)$downCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Monitor Configuration')
                    ->description('Set up uptime monitoring for your websites, APIs, or services. Get notified when something goes down.')
                    ->icon('heroicon-o-heart')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('ai_monitor_help')
                            ->label('Which monitor type?')
                            ->icon('heroicon-m-sparkles')
                            ->color('primary')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("Explain the different health monitor types: HTTP, HTTPS, TCP, Ping, SSL Expiry, and Heartbeat. When should I use each? What is the difference between HTTP and HTTPS monitoring?")',
                            ]),
                        Forms\Components\Actions\Action::make('ai_best_practices')
                            ->label('Monitoring tips')
                            ->icon('heroicon-m-sparkles')
                            ->color('gray')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("What are best practices for uptime monitoring? How often should I check? What threshold settings should I use to avoid false positives? How do I set up effective alerting without alert fatigue?")',
                            ]),
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('My Website Monitor'),
                        Forms\Components\Select::make('type')
                            ->options([
                                HealthMonitor::TYPE_HTTP => 'HTTP',
                                HealthMonitor::TYPE_HTTPS => 'HTTPS (SSL Verified)',
                                HealthMonitor::TYPE_TCP => 'TCP Port',
                                HealthMonitor::TYPE_PING => 'Ping (ICMP)',
                                HealthMonitor::TYPE_SSL_EXPIRY => 'SSL Certificate Expiry',
                                HealthMonitor::TYPE_HEARTBEAT => 'Heartbeat (Cron)',
                            ])
                            ->required()
                            ->live()
                            ->default(HealthMonitor::TYPE_HTTPS),
                        Forms\Components\Select::make('server_id')
                            ->relationship('server', 'name', fn (Builder $query) =>
                                $query->where('team_id', Filament::getTenant()?->id)
                                    ->where('status', 'active'))
                            ->searchable()
                            ->preload()
                            ->helperText('Optional: Associate with a server'),
                        Forms\Components\Select::make('web_app_id')
                            ->relationship('webApp', 'name', fn (Builder $query) =>
                                $query->whereHas('server', fn ($q) =>
                                    $q->where('team_id', Filament::getTenant()?->id)))
                            ->searchable()
                            ->preload()
                            ->helperText('Optional: Associate with a web app'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('HTTP/HTTPS Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('url')
                            ->url()
                            ->required()
                            ->placeholder('https://example.com/health'),
                        Forms\Components\KeyValue::make('settings.headers')
                            ->label('Custom Headers (Optional)')
                            ->keyLabel('Header Name')
                            ->valueLabel('Header Value')
                            ->addButtonLabel('Add Header'),
                        Forms\Components\TextInput::make('settings.keyword')
                            ->label('Required Keyword (Optional)')
                            ->placeholder('OK')
                            ->helperText('Response must contain this text'),
                        Forms\Components\TextInput::make('settings.max_response_time')
                            ->label('Max Response Time (Optional)')
                            ->numeric()
                            ->suffix('ms')
                            ->placeholder('5000')
                            ->helperText('Alert if response takes longer'),
                    ])
                    ->visible(fn (Get $get) => in_array($get('type'), [HealthMonitor::TYPE_HTTP, HealthMonitor::TYPE_HTTPS])),

                Forms\Components\Section::make('TCP Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('settings.host')
                            ->label('Host')
                            ->required()
                            ->placeholder('192.168.1.1 or example.com'),
                        Forms\Components\TextInput::make('settings.port')
                            ->label('Port')
                            ->numeric()
                            ->required()
                            ->placeholder('3306'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('type') === HealthMonitor::TYPE_TCP),

                Forms\Components\Section::make('Ping Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('settings.host')
                            ->label('Host')
                            ->required()
                            ->placeholder('8.8.8.8 or example.com'),
                        Forms\Components\TextInput::make('settings.count')
                            ->label('Ping Count')
                            ->numeric()
                            ->default(3)
                            ->minValue(1)
                            ->maxValue(10),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('type') === HealthMonitor::TYPE_PING),

                Forms\Components\Section::make('SSL Certificate Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('url')
                            ->label('Domain')
                            ->required()
                            ->placeholder('example.com'),
                        Forms\Components\TextInput::make('settings.warning_days')
                            ->label('Warning Days Before Expiry')
                            ->numeric()
                            ->default(30)
                            ->helperText('Alert when certificate expires within this many days'),
                        Forms\Components\TextInput::make('settings.port')
                            ->label('Port')
                            ->numeric()
                            ->default(443)
                            ->placeholder('443'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => $get('type') === HealthMonitor::TYPE_SSL_EXPIRY),

                Forms\Components\Section::make('Heartbeat Configuration')
                    ->schema([
                        Forms\Components\Placeholder::make('heartbeat_info')
                            ->label('How it works')
                            ->content('A unique URL will be generated. Your cron job should ping this URL on each successful run. If no ping is received within the interval, the monitor will be marked as down.'),
                        Forms\Components\Placeholder::make('heartbeat_url_display')
                            ->label('Heartbeat URL')
                            ->content(fn ($record) => $record?->heartbeat_url ?? 'Will be generated after creation')
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->visible(fn (Get $get) => $get('type') === HealthMonitor::TYPE_HEARTBEAT),

                Forms\Components\Section::make('Check Settings')
                    ->schema([
                        Forms\Components\Select::make('interval_seconds')
                            ->options([
                                60 => 'Every 1 minute',
                                120 => 'Every 2 minutes',
                                300 => 'Every 5 minutes',
                                600 => 'Every 10 minutes',
                                900 => 'Every 15 minutes',
                                1800 => 'Every 30 minutes',
                                3600 => 'Every hour',
                            ])
                            ->default(300)
                            ->required(),
                        Forms\Components\TextInput::make('timeout_seconds')
                            ->numeric()
                            ->default(30)
                            ->minValue(5)
                            ->maxValue(120)
                            ->suffix('seconds')
                            ->helperText('Time to wait for response'),
                        Forms\Components\TextInput::make('failure_threshold')
                            ->numeric()
                            ->default(3)
                            ->minValue(1)
                            ->maxValue(10)
                            ->helperText('Consecutive failures before alerting'),
                        Forms\Components\TextInput::make('recovery_threshold')
                            ->numeric()
                            ->default(2)
                            ->minValue(1)
                            ->maxValue(10)
                            ->helperText('Consecutive successes before recovery'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Disable to pause monitoring'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => HealthMonitor::TYPE_HTTP,
                        'success' => HealthMonitor::TYPE_HTTPS,
                        'info' => HealthMonitor::TYPE_TCP,
                        'gray' => HealthMonitor::TYPE_PING,
                        'warning' => HealthMonitor::TYPE_SSL_EXPIRY,
                        'purple' => HealthMonitor::TYPE_HEARTBEAT,
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        HealthMonitor::TYPE_HTTP => 'HTTP',
                        HealthMonitor::TYPE_HTTPS => 'HTTPS',
                        HealthMonitor::TYPE_TCP => 'TCP',
                        HealthMonitor::TYPE_PING => 'Ping',
                        HealthMonitor::TYPE_SSL_EXPIRY => 'SSL',
                        HealthMonitor::TYPE_HEARTBEAT => 'Heartbeat',
                        default => $state,
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => HealthMonitor::STATUS_UP,
                        'danger' => HealthMonitor::STATUS_DOWN,
                        'warning' => HealthMonitor::STATUS_PENDING,
                        'gray' => HealthMonitor::STATUS_PAUSED,
                    ]),
                Tables\Columns\TextColumn::make('check_target')
                    ->label('Target')
                    ->limit(40),
                Tables\Columns\TextColumn::make('last_response_time')
                    ->label('Response')
                    ->suffix('ms')
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state < 300 => 'success',
                        $state < 1000 => 'warning',
                        default => 'danger',
                    })
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('uptime_24h')
                    ->label('Uptime 24h')
                    ->suffix('%')
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state >= 99 => 'success',
                        $state >= 90 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 2) : null)
                    ->placeholder('-')
                    ->tooltip(fn (HealthMonitor $record) => sprintf(
                        "7d: %s%% | 30d: %s%%",
                        $record->uptime_7d !== null ? number_format($record->uptime_7d, 2) : '-',
                        $record->uptime_30d !== null ? number_format($record->uptime_30d, 2) : '-'
                    )),
                Tables\Columns\TextColumn::make('last_check_at')
                    ->label('Last Check')
                    ->since()
                    ->sortable(),
                                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        HealthMonitor::TYPE_HTTP => 'HTTP',
                        HealthMonitor::TYPE_HTTPS => 'HTTPS',
                        HealthMonitor::TYPE_TCP => 'TCP',
                        HealthMonitor::TYPE_PING => 'Ping',
                        HealthMonitor::TYPE_SSL_EXPIRY => 'SSL Expiry',
                        HealthMonitor::TYPE_HEARTBEAT => 'Heartbeat',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        HealthMonitor::STATUS_UP => 'Up',
                        HealthMonitor::STATUS_DOWN => 'Down',
                        HealthMonitor::STATUS_PENDING => 'Pending',
                        HealthMonitor::STATUS_PAUSED => 'Paused',
                    ]),
                Tables\Filters\SelectFilter::make('server_id')
                    ->relationship('server', 'name')
                    ->label('Server'),
            ])
            ->actions([
                // AI Troubleshoot for down monitors
                Tables\Actions\Action::make('ai_troubleshoot')
                    ->label('AI Diagnose')
                    ->icon('heroicon-o-sparkles')
                    ->color('danger')
                    ->visible(fn (HealthMonitor $record) => $record->status === HealthMonitor::STATUS_DOWN && config('ai.enabled'))
                    ->url('#')
                    ->extraAttributes(fn (HealthMonitor $record) => [
                        'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("My ' . e($record->type) . ' health monitor \'' . e($record->name) . '\' for ' . e($record->check_target) . ' is DOWN. Last error: ' . e($record->last_error ?? 'Unknown') . '. Response time: ' . e($record->last_response_time ?? 'N/A') . 'ms. Help me diagnose why it is failing and how to fix it.")',
                    ]),

                // AI Optimize for slow monitors
                Tables\Actions\Action::make('ai_optimize')
                    ->label('Slow?')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->visible(fn (HealthMonitor $record) => $record->status === HealthMonitor::STATUS_UP && $record->last_response_time > 1000 && config('ai.enabled'))
                    ->url('#')
                    ->extraAttributes(fn (HealthMonitor $record) => [
                        'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("My monitor \'' . e($record->name) . '\' for ' . e($record->check_target) . ' is responding slowly (' . e($record->last_response_time) . 'ms). What could cause slow response times and how can I improve performance?")',
                    ]),

                Tables\Actions\Action::make('check_now')
                    ->label('Check Now')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (HealthMonitor $record) {
                        $service = app(\App\Services\UptimeMonitor::class);
                        $result = $service->check($record);

                        if ($result->success) {
                            $record->markUp();
                        } else {
                            $record->markDown($result->error);
                        }
                    }),
                                Tables\Actions\Action::make('toggle')
                    ->label(fn (HealthMonitor $record) => $record->is_active ? 'Pause' : 'Resume')
                    ->icon(fn (HealthMonitor $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (HealthMonitor $record) => $record->is_active ? 'warning' : 'success')
                    ->action(function (HealthMonitor $record) {
                        $record->update([
                            'is_active' => !$record->is_active,
                            'status' => $record->is_active ? HealthMonitor::STATUS_PAUSED : HealthMonitor::STATUS_PENDING,
                        ]);
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-heart')
            ->emptyStateHeading('No health monitors')
            ->emptyStateDescription('Create monitors to track uptime of your websites, APIs, SSL certificates, and services. Get instant alerts when issues occur.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Monitor')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHealthMonitors::route('/'),
            'create' => Pages\CreateHealthMonitor::route('/create'),
            'view' => Pages\ViewHealthMonitor::route('/{record}'),
            'edit' => Pages\EditHealthMonitor::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('team_id', Filament::getTenant()?->id);
    }
}
