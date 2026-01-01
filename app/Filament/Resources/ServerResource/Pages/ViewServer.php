<?php

namespace App\Filament\Resources\ServerResource\Pages;

use App\Filament\Resources\ServerResource;
use App\Filament\Resources\ServerResource\Widgets\ServerMetricsOverview;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use App\Models\Server;
use App\Models\ServerProvisioningStep;
use Livewire\Attributes\On;

class ViewServer extends ViewRecord
{
    protected static string $resource = ServerResource::class;

    // Poll every 3 seconds when server is pending/provisioning
    public function getListeners(): array
    {
        if ($this->record && ($this->record->isPending() || $this->record->isProvisioning())) {
            return ['$refresh'];
        }

        return [];
    }

    public function getPollingInterval(): ?string
    {
        if ($this->record && ($this->record->isPending() || $this->record->isProvisioning())) {
            return '3s';
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            // AI Diagnose Server Action
            Actions\Action::make('ai_diagnose')
                ->label('Diagnose with AI')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn (Server $record) => $record->isActive() && config('ai.enabled'))
                ->extraAttributes(fn () => [
                    'x-data' => '',
                    'x-on:click.prevent' => 'openAiChat(' . json_encode(
                        "Analyze the health of my server '{$this->record->name}' (IP: {$this->record->ip_address}). " .
                        "Current stats: CPU " . ($this->record->latestStats()?->cpu_percent ?? 'N/A') . "%, " .
                        "Memory " . ($this->record->latestStats()?->memory_percent ?? 'N/A') . "%, " .
                        "Disk " . ($this->record->latestStats()?->disk_percent ?? 'N/A') . "%. " .
                        "OS: " . ($this->record->os_name ?? 'Unknown') . " " . ($this->record->os_version ?? '') . ". " .
                        "Identify any issues and suggest optimizations."
                    ) . ')',
                ]),

            // AI Troubleshoot Connection (for offline/failed servers)
            Actions\Action::make('ai_troubleshoot')
                ->label('Troubleshoot with AI')
                ->icon('heroicon-o-sparkles')
                ->color('danger')
                ->visible(fn (Server $record) => ($record->isOffline() || $record->isFailed()) && config('ai.enabled'))
                ->extraAttributes(fn () => [
                    'x-data' => '',
                    'x-on:click.prevent' => 'openAiChat(' . json_encode(
                        "My server '{$this->record->name}' (IP: {$this->record->ip_address}) is {$this->record->status}. " .
                        "Last heartbeat was " . ($this->record->last_heartbeat_at?->diffForHumans() ?? 'never') . ". " .
                        "Help me troubleshoot why the server is not connecting and what I can check."
                    ) . ')',
                ]),

            // AI Diagnose Provisioning Failure
            Actions\Action::make('ai_provisioning_help')
                ->label('Help with Provisioning')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->visible(fn (Server $record) => ($record->isPending() || $record->isProvisioning()) && config('ai.enabled'))
                ->extraAttributes(fn () => [
                    'x-data' => '',
                    'x-on:click.prevent' => 'openAiChat(' . json_encode(
                        "I'm trying to provision a new server '{$this->record->name}' with SiteKit. " .
                        "Current status: {$this->record->status}. " .
                        "Help me understand the provisioning process and troubleshoot if there are any issues."
                    ) . ')',
                ]),

            Actions\EditAction::make(),
            Actions\Action::make('refresh_token')
                ->label('Refresh Token')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (Server $record) {
                    $record->regenerateAgentToken();
                    Notification::make()
                        ->title('Token refreshed')
                        ->body('A new provisioning token has been generated.')
                        ->success()
                        ->send();
                })
                ->visible(fn (Server $record) => $record->isPending()),
            Actions\Action::make('reinstall_agent')
                ->label('Reinstall Agent')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reinstall Agent')
                ->modalDescription('This will regenerate the agent token and allow you to reinstall the agent on this server.')
                ->action(function (Server $record) {
                    $record->update([
                        'status' => Server::STATUS_PENDING,
                    ]);
                    $record->regenerateAgentToken();
                    Notification::make()
                        ->title('Agent reinstallation enabled')
                        ->body('Run the provisioning command on your server to reinstall the agent.')
                        ->success()
                        ->send();
                })
                ->visible(fn (Server $record) => !$record->isPending()),

            // Restore Server - removes all SiteKit components
            Actions\Action::make('restore_server')
                ->label('Restore Server')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (Server $record) => $record->isActive() || $record->isOffline() || $record->isFailed())
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('danger')
                ->modalHeading('Restore Server to Original State')
                ->modalDescription(fn (Server $record) =>
                    "This will remove ALL SiteKit components from '{$record->name}' including:\n\n" .
                    "• All web applications and their files\n" .
                    "• All databases (MySQL, MariaDB, PostgreSQL)\n" .
                    "• PHP, Nginx, Redis, Supervisor, and other services\n" .
                    "• SSL certificates\n" .
                    "• The SiteKit agent\n\n" .
                    "This action cannot be undone. The server will be reset to a clean state."
                )
                ->form([
                    \Filament\Forms\Components\Checkbox::make('confirm_remove_packages')
                        ->label('Remove all installed packages (PHP, Nginx, MySQL, etc.)')
                        ->default(true)
                        ->required(),
                    \Filament\Forms\Components\Checkbox::make('confirm_remove_data')
                        ->label('Remove all web app files and data')
                        ->default(true)
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('confirm_server_name')
                        ->label('Type the server name to confirm')
                        ->placeholder(fn (Server $record) => $record->name)
                        ->required()
                        ->rules([
                            fn (Server $record) => function (string $attribute, $value, $fail) use ($record) {
                                if ($value !== $record->name) {
                                    $fail('The server name does not match.');
                                }
                            },
                        ]),
                ])
                ->action(function (Server $record, array $data) {
                    $record->dispatchRestore(
                        removePackages: $data['confirm_remove_packages'] ?? true,
                        removeData: $data['confirm_remove_data'] ?? true,
                    );

                    Notification::make()
                        ->title('Server Restore Initiated')
                        ->body("Server '{$record->name}' is being restored to its original state. This may take several minutes.")
                        ->warning()
                        ->send();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Server Information')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('ip_address')
                            ->label('IP Address')
                            ->copyable()
                            ->placeholder('Pending'),
                        TextEntry::make('ssh_port')
                            ->label('SSH Port'),
                        TextEntry::make('provider')
                            ->badge(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                Server::STATUS_PENDING => 'warning',
                                Server::STATUS_PROVISIONING => 'info',
                                Server::STATUS_ACTIVE => 'success',
                                Server::STATUS_OFFLINE, Server::STATUS_FAILED => 'danger',
                                default => 'gray',
                            }),
                        IconEntry::make('connected')
                            ->label('Connected')
                            ->boolean()
                            ->getStateUsing(fn (Server $record): bool => $record->isConnected()),
                    ])
                    ->columns(3),

                Section::make('Connect Your Server')
                    ->description('Run this command on your server to install the SiteKit agent')
                    ->icon('heroicon-o-command-line')
                    ->schema([
                        TextEntry::make('provisioning_command')
                            ->label('Installation Command')
                            ->getStateUsing(fn (Server $record): string => $record->getProvisioningCommand())
                            ->copyable()
                            ->fontFamily('mono')
                            ->size('lg')
                            ->helperText('Copy and paste this command into your server terminal as root.'),
                        TextEntry::make('token_expires')
                            ->label('Token Expires')
                            ->getStateUsing(fn (Server $record): string => $record->agent_token_expires_at?->diffForHumans() ?? 'Never')
                            ->color('warning')
                            ->helperText('After expiration, click "Refresh Token" to generate a new one.'),
                    ])
                    ->visible(fn (Server $record): bool => $record->isPending())
                    ->collapsible(false),

                Section::make('Provisioning Status')
                    ->description('The agent is being installed on your server...')
                    ->icon('heroicon-o-arrow-path')
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn () => 'Installing Agent...'),
                        TextEntry::make('ip_address')
                            ->label('Detected IP Address')
                            ->copyable(),
                        TextEntry::make('last_heartbeat_at')
                            ->label('Last Activity')
                            ->since(),
                    ])
                    ->columns(3)
                    ->visible(fn (Server $record): bool => $record->isProvisioning() && !$record->isInstalling()),

                // Provisioning Progress Section (shown during software installation)
                Section::make('Software Installation Progress')
                    ->description(fn (Server $record) => sprintf(
                        'Installing software on your server... %d%% complete',
                        $record->getProvisioningProgress()
                    ))
                    ->icon('heroicon-o-arrow-path')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('retry_failed')
                            ->label('Retry Failed')
                            ->icon('heroicon-o-arrow-path')
                            ->color('warning')
                            ->visible(fn (Server $record) => $record->provisioningSteps()
                                ->where('status', ServerProvisioningStep::STATUS_FAILED)
                                ->exists())
                            ->action(function (Server $record) {
                                $record->provisioningSteps()
                                    ->where('status', ServerProvisioningStep::STATUS_FAILED)
                                    ->each(fn (ServerProvisioningStep $step) => $step->retry());

                                Notification::make()
                                    ->title('Retrying failed steps')
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->schema([
                        ViewEntry::make('provisioning_progress')
                            ->view('filament.components.provisioning-progress')
                            ->viewData(fn (Server $record) => [
                                'steps' => $record->provisioningSteps()->get(),
                                'progress' => $record->getProvisioningProgress(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Server $record): bool => $record->isInstalling()),

                Section::make('System Information')
                    ->icon('heroicon-o-cpu-chip')
                    ->schema([
                        TextEntry::make('os_name')
                            ->label('Operating System')
                            ->placeholder('Unknown'),
                        TextEntry::make('os_version')
                            ->label('Version')
                            ->placeholder('Unknown'),
                        TextEntry::make('cpu_count')
                            ->label('CPU Cores')
                            ->placeholder('Unknown'),
                        TextEntry::make('memory_mb')
                            ->label('Memory (MB)')
                            ->numeric()
                            ->placeholder('Unknown'),
                        TextEntry::make('disk_gb')
                            ->label('Disk (GB)')
                            ->numeric()
                            ->placeholder('Unknown'),
                        TextEntry::make('last_heartbeat_at')
                            ->label('Last Heartbeat')
                            ->since()
                            ->placeholder('Never'),
                    ])
                    ->columns(3)
                    ->visible(fn (Server $record): bool => !$record->isPending()),

                Section::make('Resource Usage')
                    ->icon('heroicon-o-chart-bar')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('ai_analyze_resources')
                            ->label('Analyze with AI')
                            ->icon('heroicon-m-sparkles')
                            ->color('primary')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes(fn (Server $record) => [
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat(' . json_encode(
                                    "Analyze resource usage on my server '{$record->name}'. " .
                                    "CPU: " . ($record->latestStats()?->cpu_percent ?? 'N/A') . "%, " .
                                    "Memory: " . ($record->latestStats()?->memory_percent ?? 'N/A') . "%, " .
                                    "Disk: " . ($record->latestStats()?->disk_percent ?? 'N/A') . "%, " .
                                    "Load: " . ($record->latestStats()?->load_1m ?? 'N/A') . ". " .
                                    "What is using resources and how can I optimize?"
                                ) . ')',
                            ]),
                    ])
                    ->schema([
                        TextEntry::make('cpu_usage')
                            ->label('CPU Usage')
                            ->getStateUsing(function (Server $record): string {
                                $stats = $record->latestStats();
                                return $stats ? round($stats->cpu_percent, 1) . '%' : 'N/A';
                            })
                            ->color(fn (Server $record) => match (true) {
                                ($record->latestStats()?->cpu_percent ?? 0) > 80 => 'danger',
                                ($record->latestStats()?->cpu_percent ?? 0) > 60 => 'warning',
                                default => 'success',
                            })
                            ->suffix(fn (Server $record) => ($record->latestStats()?->cpu_percent ?? 0) > 60 && config('ai.enabled')
                                ? view('filament.components.ai-why-link', [
                                    'prompt' => "Why is CPU usage at " . round($record->latestStats()?->cpu_percent ?? 0, 1) . "% on my server '{$record->name}'? What processes are consuming the most CPU and how can I reduce usage?",
                                ])
                                : null),
                        TextEntry::make('memory_usage')
                            ->label('Memory Usage')
                            ->getStateUsing(function (Server $record): string {
                                $stats = $record->latestStats();
                                return $stats ? round($stats->memory_percent, 1) . '%' : 'N/A';
                            })
                            ->color(fn (Server $record) => match (true) {
                                ($record->latestStats()?->memory_percent ?? 0) > 80 => 'danger',
                                ($record->latestStats()?->memory_percent ?? 0) > 60 => 'warning',
                                default => 'success',
                            })
                            ->suffix(fn (Server $record) => ($record->latestStats()?->memory_percent ?? 0) > 60 && config('ai.enabled')
                                ? view('filament.components.ai-why-link', [
                                    'prompt' => "Why is memory usage at " . round($record->latestStats()?->memory_percent ?? 0, 1) . "% on my server '{$record->name}'? What processes are using the most memory and how can I free up RAM?",
                                ])
                                : null),
                        TextEntry::make('disk_usage')
                            ->label('Disk Usage')
                            ->getStateUsing(function (Server $record): string {
                                $stats = $record->latestStats();
                                return $stats ? round($stats->disk_percent, 1) . '%' : 'N/A';
                            })
                            ->color(fn (Server $record) => match (true) {
                                ($record->latestStats()?->disk_percent ?? 0) > 80 => 'danger',
                                ($record->latestStats()?->disk_percent ?? 0) > 60 => 'warning',
                                default => 'success',
                            })
                            ->suffix(fn (Server $record) => ($record->latestStats()?->disk_percent ?? 0) > 60 && config('ai.enabled')
                                ? view('filament.components.ai-why-link', [
                                    'prompt' => "Disk usage is at " . round($record->latestStats()?->disk_percent ?? 0, 1) . "% on my server '{$record->name}'. Find the largest files and directories and suggest what can be safely deleted to free up space.",
                                ])
                                : null),
                        TextEntry::make('load_avg')
                            ->label('Load Average')
                            ->getStateUsing(function (Server $record): string {
                                $stats = $record->latestStats();
                                if (!$stats) {
                                    return 'N/A';
                                }
                                return sprintf('%.2f, %.2f, %.2f',
                                    $stats->load_1m ?? 0,
                                    $stats->load_5m ?? 0,
                                    $stats->load_15m ?? 0
                                );
                            })
                            ->color(fn (Server $record) => match (true) {
                                ($record->latestStats()?->load_1m ?? 0) > ($record->cpu_count ?? 1) * 2 => 'danger',
                                ($record->latestStats()?->load_1m ?? 0) > ($record->cpu_count ?? 1) => 'warning',
                                default => 'success',
                            })
                            ->suffix(fn (Server $record) => ($record->latestStats()?->load_1m ?? 0) > ($record->cpu_count ?? 1) && config('ai.enabled')
                                ? view('filament.components.ai-why-link', [
                                    'prompt' => "Load average is " . ($record->latestStats()?->load_1m ?? 0) . " on my server '{$record->name}' which has {$record->cpu_count} CPU cores. Why is the load so high and what can I do to reduce it?",
                                ])
                                : null),
                    ])
                    ->columns(4)
                    ->visible(fn (Server $record): bool => $record->isActive()),

                // AI Quick Actions Section
                Section::make('AI Quick Actions')
                    ->icon('heroicon-o-sparkles')
                    ->description('Ask AI to help with common server tasks')
                    ->schema([
                        ViewEntry::make('ai_quick_actions')
                            ->view('filament.components.server-ai-quick-actions')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Server $record): bool => $record->isActive() && config('ai.enabled'))
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    #[On('echo-private:server.{record.id},server.status')]
    public function handleServerStatus(): void
    {
        $this->record->refresh();

        if ($this->record->isActive()) {
            Notification::make()
                ->title('Server Connected!')
                ->body('Your server is now online and connected to SiteKit.')
                ->success()
                ->send();
        }
    }

    #[On('echo-private:server.{record.id},server.stats')]
    public function handleServerStats(): void
    {
        $this->record->refresh();
    }

    /**
     * Retry a failed provisioning step.
     */
    public function retryStep(int $stepId): void
    {
        $step = ServerProvisioningStep::find($stepId);

        if (!$step || $step->server_id !== $this->record->id) {
            Notification::make()
                ->title('Step not found')
                ->danger()
                ->send();
            return;
        }

        if (!$step->canRetry()) {
            Notification::make()
                ->title('Cannot retry this step')
                ->warning()
                ->send();
            return;
        }

        $step->retry();

        Notification::make()
            ->title('Retrying: ' . $step->step_name)
            ->success()
            ->send();
    }

    /**
     * Skip a provisioning step.
     */
    public function skipStep(int $stepId): void
    {
        $step = ServerProvisioningStep::find($stepId);

        if (!$step || $step->server_id !== $this->record->id) {
            Notification::make()
                ->title('Step not found')
                ->danger()
                ->send();
            return;
        }

        if (!$step->canSkip()) {
            Notification::make()
                ->title('Cannot skip this step')
                ->warning()
                ->send();
            return;
        }

        $step->markSkipped();
        $this->record->checkAndCompleteProvisioning();

        Notification::make()
            ->title('Skipped: ' . $step->step_name)
            ->success()
            ->send();
    }

    protected function getFooterWidgets(): array
    {
        if (!$this->record || !$this->record->isActive()) {
            return [];
        }

        return [
            ServerMetricsOverview::make([
                'record' => $this->record,
            ]),
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
