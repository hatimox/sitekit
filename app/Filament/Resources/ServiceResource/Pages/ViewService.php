<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use App\Models\AgentJob;
use App\Models\Service;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;
use App\Filament\Widgets\ServiceCpuChartWidget;
use App\Filament\Widgets\ServiceMemoryChartWidget;

class ViewService extends ViewRecord
{
    protected static string $resource = ServiceResource::class;

    public function getPollingInterval(): ?string
    {
        // Poll while service status is pending (waiting for heartbeat sync)
        $status = $this->record?->status;
        if ($status === Service::STATUS_PENDING) {
            return '5s';
        }
        return null;
    }

    protected function getFooterWidgets(): array
    {
        return [
            ServiceCpuChartWidget::make(['serviceId' => $this->record->id]),
            ServiceMemoryChartWidget::make(['serviceId' => $this->record->id]),
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 2;
    }

    protected function getHeaderActions(): array
    {
        return [
            // AI Diagnose for failed services
            Actions\Action::make('ai_diagnose')
                ->label('Diagnose with AI')
                ->icon('heroicon-o-sparkles')
                ->color('danger')
                ->visible(fn (Service $record) => $record->status === Service::STATUS_FAILED && config('ai.enabled'))
                ->extraAttributes(fn (Service $record) => [
                    'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("My ' . e($record->display_name) . ' service on server \'' . e($record->server?->name) . '\' (' . e($record->server?->ip_address) . ') has failed. Error message: ' . e($record->error_message ?? 'Unknown error') . '. Help me diagnose and fix this issue. Provide specific commands I can run.")',
                ]),

            // AI Optimize for PHP-FPM, MySQL, PostgreSQL
            Actions\Action::make('ai_optimize')
                ->label('Optimize with AI')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn (Service $record) => $record->isActive() && config('ai.enabled') && in_array($record->type, [Service::TYPE_PHP, Service::TYPE_MYSQL, Service::TYPE_MARIADB, Service::TYPE_POSTGRESQL, Service::TYPE_NGINX]))
                ->extraAttributes(fn (Service $record) => [
                    'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("Help me optimize ' . e($record->display_name) . ' on my server \'' . e($record->server?->name) . '\' which has ' . ($record->server?->cpu_count ?? 'unknown') . ' CPU cores and ' . ($record->server?->memory_mb ?? 'unknown') . ' MB RAM. Service memory usage: ' . ($record->memory_mb ?? 'unknown') . ' MB. Provide optimal configuration settings with explanations.")',
                ]),

            Actions\Action::make('restart')
                ->label('Restart')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (Service $record) => $record->isActive())
                ->requiresConfirmation()
                ->action(function (Service $record) {
                    $record->dispatchRestart();
                    Notification::make()
                        ->title('Service Restart Queued')
                        ->body("Restarting {$record->display_name}...")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reload')
                ->label('Reload')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('info')
                ->visible(fn (Service $record) => $record->isActive())
                ->action(function (Service $record) {
                    $record->dispatchReload();
                    Notification::make()
                        ->title('Service Reload Queued')
                        ->body("Reloading {$record->display_name}...")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('stop')
                ->label('Stop')
                ->icon('heroicon-o-stop')
                ->color('danger')
                // Core services (nginx, supervisor) cannot be stopped
                ->visible(fn (Service $record) => $record->isActive() && $record->canBeStopped())
                ->requiresConfirmation()
                ->modalHeading(fn (Service $record) => "Stop {$record->display_name}")
                ->modalDescription(function (Service $record) {
                    if ($record->isDatabaseEngine() && $record->hasDependentDatabases()) {
                        $count = $record->getDependentDatabases()->count();
                        $dbNames = $record->getDependentDatabases()->pluck('name')->implode(', ');
                        return "Warning: This will stop the database engine. You have {$count} database(s) using this engine: {$dbNames}. They will become inaccessible.";
                    }
                    return "Are you sure you want to stop this service?";
                })
                ->action(function (Service $record) {
                    $record->dispatchStop();
                    Notification::make()
                        ->title('Service Stop Queued')
                        ->body("Stopping {$record->display_name}...")
                        ->warning()
                        ->send();
                }),

            Actions\Action::make('start')
                ->label('Start')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (Service $record) => $record->status === Service::STATUS_STOPPED)
                ->requiresConfirmation(fn (Service $record) => $record->getConflictingServices()->isNotEmpty())
                ->modalHeading(fn (Service $record) => $record->getConflictingServices()->isNotEmpty()
                    ? "Conflict: {$record->display_name}"
                    : "Start {$record->display_name}")
                ->modalDescription(function (Service $record) {
                    $conflicts = $record->getConflictingServices();
                    if ($conflicts->isEmpty()) {
                        return null;
                    }
                    $names = $conflicts->pluck('display_name')->implode(', ');
                    return "Warning: {$names} is currently running. MySQL and MariaDB cannot run simultaneously. Starting {$record->display_name} will automatically stop {$names}.";
                })
                ->action(function (Service $record) {
                    // Stop conflicting services first
                    $conflicts = $record->getConflictingServices();
                    foreach ($conflicts as $conflict) {
                        $conflict->dispatchStop();
                    }

                    $record->dispatchStart();

                    if ($conflicts->isNotEmpty()) {
                        Notification::make()
                            ->title('Service Switch Queued')
                            ->body("Stopping conflicting services and starting {$record->display_name}...")
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Service Start Queued')
                            ->body("Starting {$record->display_name}...")
                            ->success()
                            ->send();
                    }
                }),

            Actions\Action::make('repair')
                ->label('Repair Service')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->visible(fn (Service $record) => $record->canBeRepaired())
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-wrench-screwdriver')
                ->modalHeading(fn (Service $record) => "Repair {$record->display_name}")
                ->modalDescription(function (Service $record) {
                    $desc = "This will re-run the provisioning for {$record->display_name}. ";
                    if ($record->isDatabaseEngine()) {
                        $desc .= "Database credentials will be regenerated. Your existing databases will NOT be deleted.";
                    } else {
                        $desc .= "Configuration will be reset to defaults.";
                    }
                    return $desc;
                })
                ->action(function (Service $record) {
                    $job = $record->dispatchRepair();

                    Notification::make()
                        ->title('Repair Job Queued')
                        ->body("Re-provisioning {$record->display_name}. This may take a few minutes.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('test_connection')
                ->label('Test Connection')
                ->icon('heroicon-o-signal')
                ->color('info')
                ->visible(fn (Service $record) => $record->isDatabaseEngine() && $record->isActive())
                ->action(function (Service $record) {
                    $job = AgentJob::create([
                        'server_id' => $record->server_id,
                        'team_id' => $record->server->team_id,
                        'type' => 'test_database_connection',
                        'payload' => [
                            'service_id' => $record->id,
                            'service_type' => $record->type,
                        ],
                        'priority' => 1, // High priority for quick tests
                    ]);

                    Notification::make()
                        ->title('Connection Test Queued')
                        ->body("Testing {$record->display_name} connection. Check the health status after a few seconds.")
                        ->info()
                        ->send();
                }),

            Actions\ActionGroup::make([
                Actions\Action::make('edit_config')
                    ->label('Edit Configuration')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('gray')
                    ->visible(fn (Service $record) => $record->supportsConfigEditing() && $record->isActive())
                    ->form(function (Service $record) {
                        $files = $record->getEditableConfigFiles();
                        return [
                            Forms\Components\Select::make('config_file')
                                ->label('Configuration File')
                                ->options($files)
                                ->required()
                                ->helperText('Select the configuration file to edit'),
                            Forms\Components\Textarea::make('content')
                                ->label('Content')
                                ->rows(20)
                                ->required()
                                ->placeholder('Configuration content will be loaded...')
                                ->helperText('Edit the configuration carefully. Invalid syntax may prevent the service from starting.'),
                        ];
                    })
                    ->modalHeading(fn (Service $record) => "Edit {$record->display_name} Configuration")
                    ->modalDescription('Changes require a service reload/restart to take effect. A backup is created automatically.')
                    ->modalWidth('4xl')
                    ->action(function (array $data, Service $record) {
                        // Create backup before saving
                        \App\Models\ConfigBackup::create([
                            'service_id' => $record->id,
                            'user_id' => auth()->id(),
                            'config_type' => $record->type,
                            'file_path' => $data['config_file'],
                            'content' => $data['content'],
                            'reason' => 'Before edit',
                            'is_auto' => true,
                        ]);

                        // Dispatch job to write config
                        $record->dispatchWriteConfig($data['config_file'], $data['content']);

                        Notification::make()
                            ->title('Configuration Update Queued')
                            ->body('The configuration will be updated. Remember to reload/restart the service.')
                            ->info()
                            ->send();
                    }),

                Actions\Action::make('view_backups')
                    ->label('View Config Backups')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->visible(fn (Service $record) => $record->configBackups()->exists())
                    ->modalHeading(fn (Service $record) => "{$record->display_name} Config Backups")
                    ->modalContent(function (Service $record) {
                        $backups = $record->configBackups()->latest()->limit(10)->get();
                        $html = '<div class="space-y-3">';
                        foreach ($backups as $backup) {
                            $html .= '<div class="bg-gray-100 dark:bg-gray-800 p-3 rounded-lg">';
                            $html .= '<div class="flex justify-between items-center mb-2">';
                            $html .= '<span class="font-medium">' . e(basename($backup->file_path)) . '</span>';
                            $html .= '<span class="text-sm text-gray-500">' . $backup->created_at->diffForHumans() . '</span>';
                            $html .= '</div>';
                            $html .= '<div class="text-xs text-gray-600 dark:text-gray-400">';
                            $html .= e($backup->reason ?? 'No reason specified');
                            $html .= '</div>';
                            $html .= '</div>';
                        }
                        $html .= '</div>';
                        return new HtmlString($html);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
                ->label('Config')
                ->icon('heroicon-o-cog-6-tooth')
                ->button()
                ->visible(fn (Service $record) => $record->supportsConfigEditing()),

            // PHP Extensions Management
            Actions\ActionGroup::make([
                Actions\Action::make('view_extensions')
                    ->label('View Extensions')
                    ->icon('heroicon-o-puzzle-piece')
                    ->color('gray')
                    ->modalHeading(fn (Service $record) => "PHP {$record->version} Extensions")
                    ->modalContent(function (Service $record) {
                        $installed = $record->getInstalledExtensions();
                        $available = Service::getAvailablePhpExtensions();

                        $html = '<div class="space-y-4">';
                        $html .= '<div class="font-medium text-sm text-gray-700 dark:text-gray-300">Installed Extensions (' . count($installed) . ')</div>';
                        $html .= '<div class="flex flex-wrap gap-2">';
                        foreach ($installed as $ext) {
                            $label = $available[$ext] ?? ucfirst($ext);
                            $html .= '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">';
                            $html .= e($ext);
                            $html .= '</span>';
                        }
                        $html .= '</div>';
                        $html .= '</div>';
                        return new HtmlString($html);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Actions\Action::make('install_extension')
                    ->label('Install Extension')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->visible(fn (Service $record) => $record->isActive())
                    ->form(function (Service $record) {
                        $installed = $record->getInstalledExtensions();
                        $available = Service::getAvailablePhpExtensions();
                        // Filter out already installed extensions
                        $notInstalled = array_filter($available, fn ($label, $key) => !in_array($key, $installed), ARRAY_FILTER_USE_BOTH);

                        return [
                            Forms\Components\Select::make('extension')
                                ->label('Extension')
                                ->options($notInstalled)
                                ->required()
                                ->searchable()
                                ->helperText('Select a PHP extension to install'),
                        ];
                    })
                    ->modalHeading(fn (Service $record) => "Install PHP {$record->version} Extension")
                    ->modalDescription('The extension will be installed via apt. The PHP-FPM service will need to be restarted after installation.')
                    ->action(function (array $data, Service $record) {
                        $record->dispatchInstallExtension($data['extension']);
                        $record->addExtensionToConfig($data['extension']);

                        Notification::make()
                            ->title('Extension Installation Queued')
                            ->body("Installing php{$record->version}-{$data['extension']}...")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('uninstall_extension')
                    ->label('Uninstall Extension')
                    ->icon('heroicon-o-minus')
                    ->color('danger')
                    ->visible(fn (Service $record) => $record->isActive())
                    ->form(function (Service $record) {
                        $installed = $record->getInstalledExtensions();
                        $available = Service::getAvailablePhpExtensions();
                        // Filter to only installed extensions (excluding core)
                        $coreExtensions = ['cli', 'fpm', 'common'];
                        $removable = array_filter(
                            array_combine($installed, array_map(fn ($e) => $available[$e] ?? ucfirst($e), $installed)),
                            fn ($label, $key) => !in_array($key, $coreExtensions),
                            ARRAY_FILTER_USE_BOTH
                        );

                        return [
                            Forms\Components\Select::make('extension')
                                ->label('Extension')
                                ->options($removable)
                                ->required()
                                ->searchable()
                                ->helperText('Select a PHP extension to uninstall'),
                        ];
                    })
                    ->modalHeading(fn (Service $record) => "Uninstall PHP {$record->version} Extension")
                    ->modalDescription('Warning: Uninstalling an extension may break applications that depend on it.')
                    ->requiresConfirmation()
                    ->action(function (array $data, Service $record) {
                        $record->dispatchUninstallExtension($data['extension']);
                        $record->removeExtensionFromConfig($data['extension']);

                        Notification::make()
                            ->title('Extension Removal Queued')
                            ->body("Uninstalling php{$record->version}-{$data['extension']}...")
                            ->warning()
                            ->send();
                    }),
            ])
                ->label('Extensions')
                ->icon('heroicon-o-puzzle-piece')
                ->button()
                ->visible(fn (Service $record) => $record->isPhpService()),

            // Service Logs
            Actions\ActionGroup::make([
                Actions\Action::make('ai_analyze_logs')
                    ->label('AI Log Analysis')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->visible(fn () => config('ai.enabled'))
                    ->extraAttributes(fn (Service $record) => [
                        'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("Analyze the logs for ' . e($record->display_name) . ' on my server \'' . e($record->server?->name) . '\'. What should I look for? Show me how to find recent errors and identify common issues. Log paths: ' . implode(', ', array_keys($record->getLogFiles())) . '")',
                    ]),

                Actions\Action::make('view_logs')
                    ->label('View Logs')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->form(function (Service $record) {
                        $files = $record->getLogFiles();
                        return [
                            Forms\Components\Select::make('log_file')
                                ->label('Log File')
                                ->options($files)
                                ->default(array_key_first($files))
                                ->required(),
                            Forms\Components\Select::make('lines')
                                ->label('Number of Lines')
                                ->options([
                                    50 => 'Last 50 lines',
                                    100 => 'Last 100 lines',
                                    200 => 'Last 200 lines',
                                    500 => 'Last 500 lines',
                                ])
                                ->default(100)
                                ->required(),
                        ];
                    })
                    ->modalHeading(fn (Service $record) => "{$record->display_name} Logs")
                    ->modalDescription('Request to fetch the latest log entries from the server. The agent will retrieve the log content.')
                    ->action(function (array $data, Service $record) {
                        $record->dispatchReadLog($data['log_file'], $data['lines']);

                        Notification::make()
                            ->title('Log Request Queued')
                            ->body("Fetching last {$data['lines']} lines from " . basename($data['log_file']) . "...")
                            ->info()
                            ->send();
                    }),

                Actions\Action::make('quick_error_log')
                    ->label('Quick Error Log')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->visible(fn (Service $record) => in_array($record->type, [
                        Service::TYPE_NGINX,
                        Service::TYPE_PHP,
                        Service::TYPE_MYSQL,
                        Service::TYPE_MARIADB,
                        Service::TYPE_POSTGRESQL,
                    ]))
                    ->action(function (Service $record) {
                        $errorLogPath = match ($record->type) {
                            Service::TYPE_NGINX => '/var/log/nginx/error.log',
                            Service::TYPE_PHP => "/var/log/php{$record->version}-fpm.log",
                            Service::TYPE_MYSQL, Service::TYPE_MARIADB => '/var/log/mysql/error.log',
                            Service::TYPE_POSTGRESQL => "/var/log/postgresql/postgresql-{$record->version}-main.log",
                            default => null,
                        };

                        if ($errorLogPath) {
                            $record->dispatchReadLog($errorLogPath, 100);

                            Notification::make()
                                ->title('Error Log Request Queued')
                                ->body("Fetching last 100 lines from error log...")
                                ->warning()
                                ->send();
                        }
                    }),

                Actions\Action::make('clear_log')
                    ->label('Clear Log')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->form(function (Service $record) {
                        $files = $record->getLogFiles();
                        return [
                            Forms\Components\Select::make('log_file')
                                ->label('Log File to Clear')
                                ->options($files)
                                ->required()
                                ->helperText('This will truncate the log file on the server'),
                        ];
                    })
                    ->modalHeading('Clear Log File')
                    ->modalDescription('Warning: This will permanently delete the log contents. Consider downloading the log first.')
                    ->requiresConfirmation()
                    ->action(function (array $data, Service $record) {
                        AgentJob::create([
                            'server_id' => $record->server_id,
                            'team_id' => $record->server->team_id,
                            'type' => 'clear_log',
                            'payload' => [
                                'service_id' => $record->id,
                                'file_path' => $data['log_file'],
                            ],
                        ]);

                        Notification::make()
                            ->title('Log Clear Queued')
                            ->body("Clearing " . basename($data['log_file']) . "...")
                            ->danger()
                            ->send();
                    }),
            ])
                ->label('Logs')
                ->icon('heroicon-o-document-text')
                ->button()
                ->visible(fn (Service $record) => $record->supportsLogViewing()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Service Details')
                    ->schema([
                        TextEntry::make('display_name')
                            ->label('Service'),
                        TextEntry::make('type')
                            ->badge(),
                        TextEntry::make('version')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                Service::STATUS_ACTIVE => 'success',
                                Service::STATUS_STOPPED => 'warning',
                                Service::STATUS_FAILED => 'danger',
                                default => 'gray',
                            }),
                        IconEntry::make('is_default')
                            ->label('Default Version')
                            ->boolean(),
                        TextEntry::make('server.name')
                            ->label('Server'),
                    ])
                    ->columns(3),

                Section::make('Error Information')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->schema([
                        TextEntry::make('last_error')
                            ->label('Error')
                            ->columnSpanFull()
                            ->color('danger'),
                        TextEntry::make('error_age')
                            ->label('Occurred')
                            ->placeholder('Unknown'),
                        TextEntry::make('suggested_action')
                            ->label('Suggested Action')
                            ->formatStateUsing(fn (Service $record) => $record->getSuggestedActionLabel())
                            ->badge()
                            ->color('warning')
                            ->placeholder('None'),
                        TextEntry::make('error_message')
                            ->label('Technical Details')
                            ->columnSpanFull()
                            ->visible(fn (Service $record) => $record->error_message !== null && $record->error_message !== $record->last_error),
                    ])
                    ->columns(2)
                    ->visible(fn (Service $record) => $record->hasError() || $record->error_message !== null),

                Section::make('Database Health')
                    ->icon('heroicon-o-heart')
                    ->iconColor(fn (Service $record) => match ($record->health_status) {
                        'healthy' => 'success',
                        'degraded' => 'warning',
                        'unhealthy' => 'danger',
                        default => 'gray',
                    })
                    ->schema([
                        IconEntry::make('health_status')
                            ->label('Connection Status')
                            ->icon(fn ($state) => match ($state) {
                                'healthy' => 'heroicon-o-check-circle',
                                'degraded' => 'heroicon-o-exclamation-triangle',
                                'unhealthy' => 'heroicon-o-x-circle',
                                default => 'heroicon-o-question-mark-circle',
                            })
                            ->color(fn ($state) => match ($state) {
                                'healthy' => 'success',
                                'degraded' => 'warning',
                                'unhealthy' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('database_health_response_ms')
                            ->label('Response Time')
                            ->suffix(' ms')
                            ->placeholder('N/A'),
                        TextEntry::make('database_health_error')
                            ->label('Error')
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn (Service $record) => $record->database_health_error !== null),
                    ])
                    ->columns(2)
                    ->visible(fn (Service $record) => $record->isDatabaseEngine()),

                Section::make('PHP Extensions')
                    ->schema([
                        TextEntry::make('installed_extensions')
                            ->label('')
                            ->state(fn (Service $record) => implode(', ', $record->getInstalledExtensions()))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Service $record) => $record->isPhpService())
                    ->collapsed(),

                Section::make('Configuration')
                    ->schema([
                        KeyValueEntry::make('configuration')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Service $record) => !empty($record->configuration) && !$record->isPhpService())
                    ->collapsed(),

                Section::make('Timeline')
                    ->schema([
                        TextEntry::make('installed_at')
                            ->label('Installed At')
                            ->dateTime()
                            ->placeholder('Not yet installed'),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(3),
            ]);
    }
}
