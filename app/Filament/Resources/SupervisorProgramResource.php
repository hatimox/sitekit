<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresServerForNavigation;
use App\Filament\Resources\SupervisorProgramResource\Pages;
use App\Models\SupervisorProgram;
use App\Models\WebApp;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class SupervisorProgramResource extends Resource
{
    use RequiresServerForNavigation;

    protected static ?string $model = SupervisorProgram::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Daemons';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Program Configuration')
                    ->description('Configure a background process managed by Supervisor. Perfect for queue workers, schedulers, and long-running processes.')
                    ->icon('heroicon-o-queue-list')
                    ->headerActions([
                        Forms\Components\Actions\Action::make('ai_queue_help')
                            ->label('Queue worker setup')
                            ->icon('heroicon-m-sparkles')
                            ->color('primary')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("How do I set up a Laravel queue worker with Supervisor? What command should I use? Explain options like --sleep, --tries, --max-time, --max-jobs, and --queue. How many workers should I run?")',
                            ]),
                        Forms\Components\Actions\Action::make('ai_supervisor_help')
                            ->label('Supervisor basics')
                            ->icon('heroicon-m-sparkles')
                            ->color('gray')
                            ->size('sm')
                            ->visible(fn () => config('ai.enabled'))
                            ->extraAttributes([
                                'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("Explain what Supervisor is and how it manages processes. What are autostart, autorestart, numprocs? When should I use Supervisor vs a cron job?")',
                            ]),
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->regex('/^[a-zA-Z][a-zA-Z0-9_-]*$/')
                            ->helperText('Unique name for this program (e.g., laravel-worker)')
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) =>
                                $rule->where('server_id', request()->route('server')?->id ?? request('server_id'))),
                        Forms\Components\Select::make('server_id')
                            ->relationship('server', 'name', fn (Builder $query) =>
                                $query->where('team_id', Filament::getTenant()?->id)
                                    ->where('status', 'active'))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('web_app_id')
                            ->label('Linked Web App (Optional)')
                            ->relationship('webApp', 'name', fn (Builder $query) =>
                                $query->where('team_id', Filament::getTenant()?->id))
                            ->searchable()
                            ->preload()
                            ->helperText('Link to a web app to auto-fill command and directory'),
                        Forms\Components\Textarea::make('command')
                            ->required()
                            ->rows(2)
                            ->placeholder('php artisan queue:work --sleep=3 --tries=3 --max-time=3600')
                            ->helperText('The command to run'),
                        Forms\Components\TextInput::make('directory')
                            ->placeholder('/var/www/myapp/current')
                            ->helperText('Working directory for the command'),
                        Forms\Components\TextInput::make('user')
                            ->required()
                            ->default('www-data')
                            ->helperText('System user to run the command as'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Process Settings')
                    ->description('Fine-tune how Supervisor manages this process. Defaults work for most use cases.')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\TextInput::make('numprocs')
                            ->label('Number of Processes')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(20)
                            ->helperText('Queue workers: start with 2-3'),
                        Forms\Components\Toggle::make('autostart')
                            ->label('Auto Start')
                            ->default(true)
                            ->helperText('Starts on server boot'),
                        Forms\Components\Toggle::make('autorestart')
                            ->label('Auto Restart')
                            ->default(true)
                            ->helperText('Restarts if process crashes'),
                        Forms\Components\TextInput::make('startsecs')
                            ->label('Start Seconds')
                            ->numeric()
                            ->default(1)
                            ->helperText('Wait time before marked "running"'),
                        Forms\Components\TextInput::make('stopwaitsecs')
                            ->label('Stop Wait Seconds')
                            ->numeric()
                            ->default(10)
                            ->helperText('Grace period for graceful shutdown'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Environment Variables')
                    ->description('Additional environment variables available to this process.')
                    ->icon('heroicon-o-variable')
                    ->schema([
                        Forms\Components\KeyValue::make('environment')
                            ->keyLabel('Variable')
                            ->valueLabel('Value')
                            ->addButtonLabel('Add Variable')
                            ->helperText('Overrides system environment variables'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('command')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),
                Tables\Columns\TextColumn::make('user')
                    ->badge(),
                Tables\Columns\TextColumn::make('numprocs')
                    ->label('Procs')
                    ->alignCenter(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => SupervisorProgram::STATUS_PENDING,
                        'success' => SupervisorProgram::STATUS_ACTIVE,
                        'gray' => SupervisorProgram::STATUS_STOPPED,
                        'danger' => SupervisorProgram::STATUS_FAILED,
                    ]),
                Tables\Columns\TextColumn::make('memory_mb')
                    ->label('Memory')
                    ->formatStateUsing(fn ($state) => $state ? "{$state} MB" : null)
                    ->color(fn ($state) => $state > 512 ? 'danger' : ($state > 256 ? 'warning' : 'success'))
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cpu_percent')
                    ->label('CPU')
                    ->suffix('%')
                    ->color(fn ($state) => $state > 80 ? 'danger' : ($state > 50 ? 'warning' : 'success'))
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('uptime')
                    ->label('Uptime')
                    ->since()
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('restart_count')
                    ->label('Restarts')
                    ->badge()
                    ->color(fn ($state) => $state > 5 ? 'danger' : ($state > 0 ? 'warning' : 'gray'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('webApp.name')
                    ->label('App')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        SupervisorProgram::STATUS_PENDING => 'Pending',
                        SupervisorProgram::STATUS_ACTIVE => 'Active',
                        SupervisorProgram::STATUS_STOPPED => 'Stopped',
                        SupervisorProgram::STATUS_FAILED => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('server_id')
                    ->relationship('server', 'name')
                    ->label('Server'),
            ])
            ->actions([
                // AI Troubleshoot for failed programs
                Tables\Actions\Action::make('ai_troubleshoot')
                    ->label('AI Help')
                    ->icon('heroicon-o-sparkles')
                    ->color('danger')
                    ->visible(fn (SupervisorProgram $record) => $record->status === SupervisorProgram::STATUS_FAILED && config('ai.enabled'))
                    ->url('#')
                    ->extraAttributes(fn (SupervisorProgram $record) => [
                        'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("My Supervisor program \'' . e($record->name) . '\' has failed. Command: ' . e($record->command) . '. Restarts: ' . e($record->restart_count) . '. stderr: ' . e(\Illuminate\Support\Str::limit($record->stderr_log ?? 'No output', 200)) . '. How do I fix this?")',
                    ]),

                // AI Optimize for high memory/CPU
                Tables\Actions\Action::make('ai_optimize')
                    ->label('Optimize')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->visible(fn (SupervisorProgram $record) => ($record->memory_mb > 256 || $record->cpu_percent > 50 || $record->restart_count > 5) && config('ai.enabled'))
                    ->url('#')
                    ->extraAttributes(fn (SupervisorProgram $record) => [
                        'x-data' => '',
                                'x-on:click.prevent' => 'openAiChat("My Supervisor program \'' . e($record->name) . '\' may need optimization. Command: ' . e($record->command) . '. Memory: ' . e($record->memory_mb ?? 'Unknown') . ' MB. CPU: ' . e($record->cpu_percent ?? 'Unknown') . '%. Restarts: ' . e($record->restart_count) . '. How can I optimize this worker?")',
                    ]),

                Tables\Actions\Action::make('start')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (SupervisorProgram $record) => $record->isStopped())
                    ->requiresConfirmation()
                    ->action(function (SupervisorProgram $record) {
                        $record->dispatchJob('supervisor_start', [
                            'program_id' => $record->id,
                            'name' => $record->name,
                        ]);
                        $record->update(['status' => SupervisorProgram::STATUS_PENDING]);
                    }),
                Tables\Actions\Action::make('stop')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->visible(fn (SupervisorProgram $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->action(function (SupervisorProgram $record) {
                        $record->dispatchJob('supervisor_stop', [
                            'program_id' => $record->id,
                            'name' => $record->name,
                        ]);
                        $record->update(['status' => SupervisorProgram::STATUS_STOPPED]);
                    }),
                Tables\Actions\Action::make('restart')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (SupervisorProgram $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->action(function (SupervisorProgram $record) {
                        $record->dispatchJob('supervisor_restart', [
                            'program_id' => $record->id,
                            'name' => $record->name,
                        ]);
                        Notification::make()
                            ->title('Restart requested')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('logs')
                    ->label('Logs')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->modalHeading(fn (SupervisorProgram $record) => "Logs: {$record->name}")
                    ->modalContent(fn (SupervisorProgram $record) => new HtmlString(
                        '<div class="space-y-4">' .
                        '<div>' .
                        '<label class="text-sm font-medium text-gray-700 dark:text-gray-300">stdout log</label>' .
                        '<div class="mt-1 bg-gray-900 text-green-400 p-3 rounded-lg font-mono text-xs max-h-48 overflow-y-auto">' .
                        '<pre>' . e($record->stdout_log ?? 'No stdout output') . '</pre>' .
                        '</div>' .
                        '</div>' .
                        '<div>' .
                        '<label class="text-sm font-medium text-gray-700 dark:text-gray-300">stderr log</label>' .
                        '<div class="mt-1 bg-gray-900 text-red-400 p-3 rounded-lg font-mono text-xs max-h-48 overflow-y-auto">' .
                        '<pre>' . e($record->stderr_log ?? 'No stderr output') . '</pre>' .
                        '</div>' .
                        '</div>' .
                        '</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (SupervisorProgram $record) {
                        $record->dispatchJob('supervisor_delete', [
                            'program_id' => $record->id,
                            'name' => $record->name,
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-queue-list')
            ->emptyStateHeading('No workers configured')
            ->emptyStateDescription('Create Supervisor workers to run background processes like Laravel queue workers, schedulers, or any long-running scripts.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Worker')
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
            'index' => Pages\ListSupervisorPrograms::route('/'),
            'create' => Pages\CreateSupervisorProgram::route('/create'),
            'view' => Pages\ViewSupervisorProgram::route('/{record}'),
            'edit' => Pages\EditSupervisorProgram::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('team_id', Filament::getTenant()?->id);
    }
}
